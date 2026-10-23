#!/usr/bin/env bash
# FTP-выкладка kumho-tires.ru на прод (REG.RU 31.31.196.72, docroot www/kumho-tires.ru/).
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
# каталогов в корень докрута. НЕ трогаем: .env/.htaccess/index.php/vendor/cache/logs/yandex_*.html.
# llms.txt и llms-full.txt лежат в public/ и на прод едут в корень: llms.txt ведётся руками в репозитории,
# llms-full.txt генерится `npm run generate-llms`. Раньше оба были в списке исключений, и на проде
# висели версии месячной давности — новые статьи ИИ-краулеры не видели.
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

echo "==> Прод-сборка ассетов (critical + CSS + JS + манифест картинок + llms-full)"
npm run build:critical
npm run build:css:prod
npm run build:js:prod
# Манифест размеров — build-артефакт (не в git): без пересчёта он протухает на стейдже,
# и фаза 2 зеркалит устаревший на прод (пропавшая обложка новости, 2026-08-03).
npm run build:image-manifest
# Описание для ИИ-краулеров собирается из контента: без пересборки на прод уедет вчерашний срез.
npm run generate-llms > public/llms-full.txt

HEAD_SHA="$(git rev-parse HEAD 2>/dev/null || true)"

# --- Страховка выкладки (24.09.2026) ------------------------------------------------------
# На evolute-rolfspb квота FTP кончилась посреди заливки: put отдал «452», файлы легли нулём
# байт, старое уже снеслось — сайт стал белым, а трекер трижды повторил то же самое. Здесь:
# вывод каждого шага перехватывается; «нет места» или ошибка передачи — стоп ДО следующего шага
# (удаления старого, манифестов, маркера), код 75 для «нет места» — трекер тогда не повторяет.
guard_no_space() { grep -qiE '(^|[^0-9a-f])452([^0-9a-f]|$)|не осталось свободного места|no space left|quota|insufficient (storage|disk)' <<<"$1"; }
guard_failed() { grep -qiE '^(put|mirror|mkdir|rm): .*(error|failed|Access failed|Fatal)|Fatal error|Login incorrect|530 ' <<<"$1"; }
guard_step() { # $1 — что за шаг; stdin — вывод lftp
  local out; out="$(cat | sed -E 's#ftp://[^@[:space:]/]*@#ftp://***@#g')"   # креды lftp печатает в URL
  [[ -n "$out" ]] && echo "$out"
  if guard_no_space "$out"; then
    echo "⛔ FTP ${FTP_HOST}: НЕТ МЕСТА (квота хостинга) на шаге «$1» — дальше не иду: старое не удалял, маркер не трогал."
    echo "   Нужно освободить место на хостинге и повторить выкладку. Повторять без этого бесполезно."
    exit 75
  fi
  if guard_failed "$out"; then
    echo "⛔ Ошибка передачи на шаге «$1» — дальше не иду: старое не удалял, маркер не трогал."
    exit 1
  fi
}
# ---------------------------------------------------------------------------------------------

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
lftp_k() { # stdin — команды одной сессии
  { echo "set ssl:verify-certificate no"; echo "set ftp:ssl-allow true"; echo "set net:connection-limit 1"
    echo "set net:persist-retries 0"; echo "set mirror:parallel-transfer-count 1"; cat; echo "quit"; } \
    | lftp -u "${FTP_USER},${FTP_PASS}" "${FTP_HOST}" 2>&1
}
# Порядок критичен и теперь ещё и со стопом между шагами: новые хешированные файлы → манифесты →
# изменённые файлы → снос устаревших хешей. Манифест на проде не должен указывать на незалитый
# или пустой файл (реальные 500 в окне деплоя 2026-08-01; «нет места» на evolute 24.09).
printf '%s\n' "mirror -R ${DRY} --verbose --no-symlinks --exclude-glob *manifest*.json assets/css/build/ ${FTP_DIR}assets/css/build/" \
              "mirror -R ${DRY} --verbose --no-symlinks --exclude-glob *manifest*.json assets/js/build/  ${FTP_DIR}assets/js/build/" \
  | lftp_k | guard_step "новые бандлы"
if [[ $APPLY -eq 1 ]]; then
  for manifest in assets/css/build/css-manifest.json assets/js/build/asset-manifest.json; do
    [[ -f "$ROOT/$manifest" ]] && printf 'put "%s" -o "%s"\n' "$ROOT/$manifest" "${FTP_DIR}${manifest}"
  done | lftp_k | guard_step "манифесты сборки"
  {
    for f in "${CHANGED[@]}"; do
      printf 'mkdir -p -f "%s"\n' "${FTP_DIR}$(dirname "$f")/"
      printf 'put "%s" -o "%s"\n' "$ROOT/$f" "${FTP_DIR}$f"
    done
    # Манифест картинок — после файлов, на которые он ссылается.
    if [[ -f "$ROOT/assets/img/build/image-dimensions.json" ]]; then
      printf 'mkdir -p -f "%s"\n' "${FTP_DIR}assets/img/build/"
      printf 'put "%s" -o "%s"\n' "$ROOT/assets/img/build/image-dimensions.json" "${FTP_DIR}assets/img/build/image-dimensions.json"
    fi
  } | lftp_k | guard_step "изменённые файлы"
fi
printf '%s\n' "mirror -R ${DRY} --verbose --no-symlinks --delete assets/css/build/ ${FTP_DIR}assets/css/build/" \
              "mirror -R ${DRY} --verbose --no-symlinks --delete assets/js/build/  ${FTP_DIR}assets/js/build/" \
  | lftp_k | guard_step "снос устаревших хешей"

if [[ $APPLY -eq 1 ]]; then
  lftp -u "${FTP_USER},${FTP_PASS}" "${FTP_HOST}" -e "set ssl:verify-certificate no; set ftp:ssl-allow true; put robots.txt -o ${FTP_DIR}robots.txt; put public/llms.txt -o ${FTP_DIR}llms.txt; put public/llms-full.txt -o ${FTP_DIR}llms-full.txt; rm -r ${FTP_DIR}cache/twig; bye" >/dev/null 2>&1 || true
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
