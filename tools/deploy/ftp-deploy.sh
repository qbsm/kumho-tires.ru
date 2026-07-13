#!/usr/bin/env bash
# FTP-выкладка kumho-tires.ru на прод (Timeweb 31.31.196.72, docroot www/kumho-tires.ru/).
#
# Прод — ПЛОСКАЯ структура: index.php (self-locating: projectRoot = is_dir(__DIR__/config)?__DIR__:dirname),
# рядом config/ data/ assets/ src/ templates/ vendor/, БЕЗ отдельного public/. Поэтому деплой = заливка
# контент/код-каталогов в корень докрута (index.php у прода = public/index.php побайтово, трогать не нужно).
#
# Деплоим ТОЛЬКО контент/код/сборку: config/ data/ src/ templates/ assets/{css,js}/build assets/img robots.txt.
# НЕ трогаем прод-специфику: .env (секреты), .htaccess/index.php (структура), vendor/ (зависимости — вручную),
# cache/ logs/ (рантайм), llms*.txt / yandex_*.html (генерятся/верификация). Поэтому по контенту БЕЗ --delete
# (ничего прод-специфичного не сносим); --delete только по assets/{css,js}/build — там лежат лишь хеш-сборки,
# чистим устаревшие хеши.
#
# Креды — через env (задаёт трекер deployFtp из /home/promo/.credentials/sever-avto-shiny.md либо вручную):
#   FTP_HOST=31.31.196.72 FTP_USER=... FTP_PASS=... FTP_DIR=www/kumho-tires.ru/ bash tools/deploy/ftp-deploy.sh
# По умолчанию DRY-RUN; реальная выкладка — с флагом --apply.
set -euo pipefail

APPLY=0
[[ "${1:-}" == "--apply" ]] && APPLY=1

: "${FTP_HOST:?FTP_HOST не задан}"
: "${FTP_USER:?FTP_USER не задан}"
: "${FTP_PASS:?FTP_PASS не задан}"
FTP_DIR="${FTP_DIR:-www/kumho-tires.ru/}"
case "$FTP_DIR" in */) ;; *) FTP_DIR="$FTP_DIR/";; esac

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"

command -v lftp >/dev/null 2>&1 || { echo "lftp не установлен"; exit 1; }

echo "==> Прод-сборка ассетов (critical + CSS + JS)"
npm run build:critical
npm run build:css:prod
npm run build:js:prod

DRY="--dry-run"
[[ $APPLY -eq 1 ]] && DRY=""

echo "==> lftp mirror → ${FTP_HOST}:${FTP_DIR}  (apply=${APPLY})"
# Одно соединение, без параллелизма — mirror на Timeweb лочит аккаунт при многопоточности.
lftp -u "${FTP_USER},${FTP_PASS}" "${FTP_HOST}" <<LFTP
set ssl:verify-certificate no
set ftp:ssl-allow true
set net:connection-limit 1
set net:persist-retries 0
set mirror:parallel-transfer-count 1
# Текст/код/контент-JSON — сравнение по size+time (по умолчанию). git-checkout ставит свежие mtime, поэтому
# эти каталоги перезаливаются целиком каждый деплой — но они маленькие (~2-3 МБ). ВАЖНО именно size+time:
# правку того же размера (напр. css-manifest.json — всегда 54 б, хеш той же длины) size-only сравнение
# пропустило бы → прод отдавал бы 404 на CSS. Поэтому build/ и json — только по времени.
mirror -R ${DRY} --verbose --no-symlinks config/    ${FTP_DIR}config/
mirror -R ${DRY} --verbose --no-symlinks src/       ${FTP_DIR}src/
mirror -R ${DRY} --verbose --no-symlinks templates/ ${FTP_DIR}templates/
mirror -R ${DRY} --verbose --no-symlinks data/json/ ${FTP_DIR}data/json/
mirror -R ${DRY} --verbose --no-symlinks --delete assets/css/build/ ${FTP_DIR}assets/css/build/
mirror -R ${DRY} --verbose --no-symlinks --delete assets/js/build/  ${FTP_DIR}assets/js/build/
# Тяжёлая бинарная статика (data/img 86 МБ, data/video 11 МБ, pdf, assets/img) — сравнение по размеру
# (--ignore-time): у бинарников смена контента = смена размера, поэтому size-only безопасно и НЕ гонит
# десятки МБ на каждый деплой из-за checkout-mtime (иначе таймаут трекера / лок FTP-аккаунта Timeweb).
mirror -R ${DRY} --verbose --no-symlinks --ignore-time data/img/   ${FTP_DIR}data/img/
mirror -R ${DRY} --verbose --no-symlinks --ignore-time data/video/ ${FTP_DIR}data/video/
mirror -R ${DRY} --verbose --no-symlinks --ignore-time data/docs/  ${FTP_DIR}data/docs/
mirror -R ${DRY} --verbose --no-symlinks --ignore-time assets/img/ ${FTP_DIR}assets/img/
quit
LFTP

if [[ $APPLY -eq 1 ]]; then
  echo "==> robots.txt + сброс twig-кэша на проде"
  lftp -u "${FTP_USER},${FTP_PASS}" "${FTP_HOST}" -e "set ssl:verify-certificate no; set ftp:ssl-allow true; put robots.txt -o ${FTP_DIR}robots.txt; rm -r ${FTP_DIR}cache/twig; bye" >/dev/null 2>&1 || true
fi

echo "==> Готово (apply=${APPLY})."
[[ $APPLY -eq 0 ]] && echo "(DRY-RUN — для реальной выкладки добавь --apply)"
exit 0
