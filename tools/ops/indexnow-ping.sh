#!/usr/bin/env bash
# Пинг IndexNow (Яндекс/Bing поддерживают, Google — нет): мгновенное уведомление об изменённых URL
# вместо ожидания планового обхода. Ключ подтверждается файлом public/<key>.txt (на плоском проде —
# в корне докрута; заливается вместе с llms*.txt отдельным put, деплой-скрипт public/ не трогает).
#
#   bash tools/ops/indexnow-ping.sh                       # все URL из sitemap.xml прода
#   bash tools/ops/indexnow-ping.sh https://... https://...  # только указанные URL
#
# Дёргать после каждой выкладки, меняющей контент (новость, модель, страница).
set -euo pipefail

HOST="kumho-tires.ru"
KEY="96df3cb637ac6e684ad0930c21cb781b"

URLS_FILE="$(mktemp)"
trap 'rm -f "$URLS_FILE"' EXIT

if [ "$#" -gt 0 ]; then
  printf '%s\n' "$@" > "$URLS_FILE"
else
  curl -s --noproxy '*' -m 30 "https://${HOST}/sitemap.xml" \
    | grep -o '<loc>[^<]*</loc>' | sed 's/<[^>]*>//g' > "$URLS_FILE"
fi

COUNT="$(grep -c . "$URLS_FILE" || true)"
[ "$COUNT" -eq 0 ] && { echo "URL не найдены — нечего пинговать"; exit 1; }

PAYLOAD="$(python3 - "$HOST" "$KEY" "$URLS_FILE" <<'PY'
import json, sys
host, key, path = sys.argv[1], sys.argv[2], sys.argv[3]
urls = [u.strip() for u in open(path, encoding="utf-8") if u.strip()]
print(json.dumps({
    "host": host,
    "key": key,
    "keyLocation": f"https://{host}/{key}.txt",
    "urlList": urls[:10000],
}, ensure_ascii=False))
PY
)"

# Пингуем Яндекс: по протоколу IndexNow уведомление расшаривается всем поисковикам-участникам.
# (api.indexnow.org и Bing могут отдавать 403 SiteVerificationNotCompleted, пока не провалидировали ключ.)
echo "==> IndexNow: ${COUNT} URL → yandex.com/indexnow"
curl -s --noproxy '*' -m 30 -X POST "https://yandex.com/indexnow" \
  -H 'Content-Type: application/json; charset=utf-8' \
  -d "$PAYLOAD" -o /dev/null -w "HTTP %{http_code}\n"
