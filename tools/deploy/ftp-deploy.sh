#!/usr/bin/env bash
# FTP-выкладка kumho-tires.ru на прод (Timeweb 31.31.196.72, docroot www/kumho-tires.ru/).
#
# ДВЕ ФАЗЫ:
#   Фаза 1 (foreground, быстро)  — собрать ассеты и залить ТОЛЬКО модифицированное:
#       * собранную статику assets/{css,js}/build (хеши гитигнор, меняются каждую сборку) с чисткой старых;
#       * отслеживаемые git-файлы, изменённые с прошлого деплоя (маркер logs/ftp-last-deployed);
#     эту фазу ждёт трекер — она короткая, деплой отчитывается сразу.
#   Фаза 2 (background) — ftp-reconcile.sh: полная mirror-сверка остального (весь контент/код), запускается
#     detached и НЕ блокирует трекер; по завершении двигает маркер на текущий HEAD.
#
# Прод — ПЛОСКАЯ структура: index.php (self-locating: projectRoot = is_dir(__DIR__/config)?__DIR__:dirname),
# рядом config/ data/ assets/ src/ templates/ vendor/, без public/. Поэтому деплой = заливка контент/код-
# каталогов в корень докрута. НЕ трогаем: .env/.htaccess/index.php/vendor/cache/logs/llms*.txt/yandex_*.html.
#
# Креды — через env (задаёт трекер deployFtp из /home/promo/.credentials/sever-avto-shiny.md либо вручную):
#   FTP_HOST=31.31.196.72 FTP_USER=... FTP_PASS=... FTP_DIR=www/kumho-tires.ru/ bash tools/deploy/ftp-deploy.sh --apply
# По умолчанию DRY-RUN; реальная выкладка — с флагом --apply.
set -euo pipefail

APPLY=0
[[ "${1:-}" == "--apply" ]] && APPLY=1

: "${FTP_HOST:?FTP_HOST не задан}"
: "${FTP_USER:?FTP_USER не задан}"
: "${FTP_PASS:?FTP_PASS не задан}"
FTP_DIR="${FTP_DIR:-www/kumho-tires.ru/}"
case "$FTP_DIR" in */) ;; *) FTP_DIR="$FTP_DIR/";; esac
export FTP_HOST FTP_USER FTP_PASS FTP_DIR

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

command -v lftp >/dev/null 2>&1 || { echo "lftp не установлен"; exit 1; }

MARKER="logs/ftp-last-deployed"

echo "==> Прод-сборка ассетов (critical + CSS + JS)"
npm run build:critical
npm run build:css:prod
npm run build:js:prod

HEAD_SHA="$(git rev-parse HEAD 2>/dev/null || true)"

# --- Список модифицированного (отслеживаемые файлы с прошлого деплоя) ---
CHANGED=()
if [[ -n "${HEAD_SHA}" && -s "$MARKER" ]]; then
  LAST="$(tr -dc 'a-f0-9' < "$MARKER" | head -c 40)"
  if [[ -n "$LAST" ]] && git cat-file -e "${LAST}^{commit}" 2>/dev/null; then
    while IFS= read -r f; do
      [[ -n "$f" && -f "$f" ]] && CHANGED+=("$f")
    done < <(git diff --name-only --diff-filter=ACMRT "$LAST" HEAD -- config data src templates assets/img assets/fonts robots.txt 2>/dev/null)
  else
    echo "==> маркер невалиден — фаза 1 зальёт только сборку, остальное доберёт фоновая сверка"
  fi
else
  echo "==> нет маркера — фаза 1 зальёт только сборку, остальное доберёт фоновая сверка"
fi

DRY="--dry-run"
[[ $APPLY -eq 1 ]] && DRY=""

echo "==> Фаза 1: сборка + ${#CHANGED[@]} изменённых файлов → ${FTP_HOST}:${FTP_DIR} (apply=${APPLY})"
{
  echo "set ssl:verify-certificate no"
  echo "set ftp:ssl-allow true"
  echo "set net:connection-limit 1"
  echo "set net:persist-retries 0"
  echo "set mirror:parallel-transfer-count 1"
  # Собранная статика — всегда (хеши гитигнор). Порядок критичен: сначала новые хешированные
  # файлы, только потом манифесты. Иначе манифест на проде уже указывает на ещё не залитый файл,
  # и AssetExtension валит рендер («Ассет 'main.css' отсутствует в манифесте») — реальные 500 в
  # окне деплоя, поймано логом 2026-08-01. --delete устаревших хешей — последним шагом.
  echo "mirror -R ${DRY} --verbose --no-symlinks --exclude-glob *manifest*.json assets/css/build/ ${FTP_DIR}assets/css/build/"
  echo "mirror -R ${DRY} --verbose --no-symlinks --exclude-glob *manifest*.json assets/js/build/  ${FTP_DIR}assets/js/build/"
  if [[ $APPLY -eq 1 ]]; then
    for manifest in assets/css/build/css-manifest.json assets/js/build/asset-manifest.json; do
      [[ -f "$ROOT/$manifest" ]] && printf 'put "%s" -o "%s"\n' "$ROOT/$manifest" "${FTP_DIR}${manifest}"
    done
  fi
  echo "mirror -R ${DRY} --verbose --no-symlinks --delete assets/css/build/ ${FTP_DIR}assets/css/build/"
  echo "mirror -R ${DRY} --verbose --no-symlinks --delete assets/js/build/  ${FTP_DIR}assets/js/build/"
  # Изменённые отслеживаемые файлы — только при реальной выкладке (mkdir/put меняют прод).
  if [[ $APPLY -eq 1 ]]; then
    for f in "${CHANGED[@]}"; do
      printf 'mkdir -p -f "%s"\n' "${FTP_DIR}$(dirname "$f")/"
      printf 'put "%s" -o "%s"\n' "$ROOT/$f" "${FTP_DIR}$f"
    done
  fi
  echo "quit"
} | lftp -u "${FTP_USER},${FTP_PASS}" "${FTP_HOST}"

if [[ $APPLY -eq 1 ]]; then
  lftp -u "${FTP_USER},${FTP_PASS}" "${FTP_HOST}" -e "set ssl:verify-certificate no; set ftp:ssl-allow true; put robots.txt -o ${FTP_DIR}robots.txt; rm -r ${FTP_DIR}cache/twig; bye" >/dev/null 2>&1 || true
  echo "==> Фаза 1 готова: залито ${#CHANGED[@]} изменённых файлов + сборка, twig-кэш сброшен."
else
  echo "(DRY-RUN — фаза 1 залила бы ${#CHANGED[@]} изменённых файлов + сборку; для реальной выкладки --apply)"
fi

# --- Фаза 2: полная сверка остального в ФОНЕ (detached, не блокирует трекер) ---
if [[ $APPLY -eq 1 ]]; then
  mkdir -p logs
  setsid bash "$ROOT/tools/deploy/ftp-reconcile.sh" >> "$ROOT/logs/ftp-reconcile.log" 2>&1 </dev/null &
  echo "==> Фаза 2: фоновая полная сверка запущена (logs/ftp-reconcile.log; маркер обновит по завершении)."
fi

exit 0
