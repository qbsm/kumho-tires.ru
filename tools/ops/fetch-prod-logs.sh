#!/usr/bin/env bash
# Забирает логи приложения с прода (REG.RU, только FTP — ssh на хостинге нет) в logs/prod/.
#
#   FTP_HOST=31.31.196.72 FTP_USER=... FTP_PASS=... bash tools/ops/fetch-prod-logs.sh
#   FTP_HOST=... FTP_USER=... FTP_PASS=... bash tools/ops/fetch-prod-logs.sh --tail
#
# Креды — /home/promo/.credentials/sever-avto-shiny.md на sel (тот же аккаунт, что у деплоя).
# С --tail печатает последние записи по убыванию времени, разворачивая JSON-строки Monolog.
set -euo pipefail

: "${FTP_HOST:?FTP_HOST не задан}"
: "${FTP_USER:?FTP_USER не задан}"
: "${FTP_PASS:?FTP_PASS не задан}"
FTP_DIR="${FTP_DIR:-www/kumho-tires.ru/}"
case "$FTP_DIR" in */) ;; *) FTP_DIR="$FTP_DIR/";; esac

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
DEST="$ROOT/logs/prod"
mkdir -p "$DEST"

command -v lftp >/dev/null 2>&1 || { echo "lftp не установлен"; exit 1; }

lftp -u "$FTP_USER,$FTP_PASS" "$FTP_HOST" -e "
  set ftp:ssl-allow no;
  set net:max-retries 2;
  mirror --only-newer --no-perms --parallel=1 ${FTP_DIR}logs $DEST;
  bye
" >/dev/null

echo "Логи прода → $DEST"
ls -lt "$DEST" | head -20

if [ "${1:-}" = "--tail" ]; then
  LATEST="$(ls -t "$DEST"/app-*.log 2>/dev/null | head -1 || true)"
  [ -z "$LATEST" ] && { echo "app-*.log не найден"; exit 0; }
  echo
  echo "=== последние записи: $(basename "$LATEST") ==="
  tail -50 "$LATEST" | python3 -c "
import json, sys
for line in sys.stdin:
    line = line.strip()
    if not line:
        continue
    try:
        entry = json.loads(line)
    except ValueError:
        print(line)
        continue
    ctx = entry.get('context', {})
    where = ctx.get('path') or ''
    status = ctx.get('status') or ''
    print(f\"{entry.get('datetime','')} {entry.get('level_name','')} {status} {where} — {entry.get('message','')[:160]}\")
    for key in ('referer', 'request_id', 'file', 'line'):
        if ctx.get(key):
            print(f'    {key}: {ctx[key]}')
"
fi
