#!/usr/bin/env bash
# Фоновая полная сверка прода kumho-tires.ru со стейджем — «доливает остальное» после быстрой фазы 1
# (см. ftp-deploy.sh). Запускается detached, НЕ блокирует трекер. Всегда реальная выкладка.
#
# Полный безопасный mirror контента/кода в www/kumho-tires.ru/:
#   * config/ src/ templates/ data/json/ — сравнение по size+time (правку того же размера, напр.
#     css-manifest.json всегда 54 б, size-only пропустил бы → нужно по времени);
#   * тяжёлая бинарщина data/img (86 МБ) / data/video (11 МБ) / data/docs / assets/img — по --ignore-time
#     (размер), иначе checkout-mtime гонит десятки МБ каждый раз → таймаут/лок FTP-аккаунта Timeweb;
#   * assets/{css,js}/build — с --delete (чистка устаревших хешей).
# БЕЗ --delete по контенту (беречь прод-специфику: .env, vendor, llms*.txt, yandex_*.html, cache, logs).
# По успеху двигает маркер logs/ftp-last-deployed на текущий HEAD (следующая фаза 1 считает дельту отсюда).
# flock -n — если сверка уже идёт, второй запуск тихо выходит (не лочим аккаунт параллелью).
set -eo pipefail

: "${FTP_HOST:?FTP_HOST не задан}"
: "${FTP_USER:?FTP_USER не задан}"
: "${FTP_PASS:?FTP_PASS не задан}"
FTP_DIR="${FTP_DIR:-www/kumho-tires.ru/}"
case "$FTP_DIR" in */) ;; *) FTP_DIR="$FTP_DIR/";; esac

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT"
mkdir -p logs

exec 9> logs/.ftp-reconcile.lock
if ! flock -n 9; then
  echo "$(printf '%(%F %T)T' -1) reconcile: сверка уже идёт — пропуск"
  exit 0
fi

MARKER="logs/ftp-last-deployed"
HEAD_SHA="$(git rev-parse HEAD 2>/dev/null || true)"
echo "=== $(printf '%(%F %T)T' -1) reconcile start HEAD=${HEAD_SHA} → ${FTP_HOST}:${FTP_DIR} ==="

lftp -u "${FTP_USER},${FTP_PASS}" "${FTP_HOST}" <<LFTP
set ssl:verify-certificate no
set ftp:ssl-allow true
set net:connection-limit 1
set net:persist-retries 0
set mirror:parallel-transfer-count 1
mirror -R --verbose --no-symlinks config/    ${FTP_DIR}config/
mirror -R --verbose --no-symlinks src/       ${FTP_DIR}src/
mirror -R --verbose --no-symlinks templates/ ${FTP_DIR}templates/
mirror -R --verbose --no-symlinks data/json/ ${FTP_DIR}data/json/
mirror -R --verbose --no-symlinks --delete assets/css/build/ ${FTP_DIR}assets/css/build/
mirror -R --verbose --no-symlinks --delete assets/js/build/  ${FTP_DIR}assets/js/build/
mirror -R --verbose --no-symlinks --ignore-time data/img/   ${FTP_DIR}data/img/
mirror -R --verbose --no-symlinks --ignore-time data/video/ ${FTP_DIR}data/video/
mirror -R --verbose --no-symlinks --ignore-time data/docs/    ${FTP_DIR}data/docs/
mirror -R --verbose --no-symlinks --ignore-time assets/img/   ${FTP_DIR}assets/img/
mirror -R --verbose --no-symlinks --ignore-time assets/fonts/ ${FTP_DIR}assets/fonts/
quit
LFTP

[[ -n "${HEAD_SHA}" ]] && printf '%s\n' "${HEAD_SHA}" > "$MARKER"
echo "=== $(printf '%(%F %T)T' -1) reconcile done, marker=${HEAD_SHA} ==="
