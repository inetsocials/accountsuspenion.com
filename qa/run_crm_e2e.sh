#!/usr/bin/env bash
# Fresh CRM + website test environment, then the Playwright end-to-end suite.
# Usage: qa/run_crm_e2e.sh ENV_DIR [PORT]
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
ENV="${1:?env dir}"; PORT="${2:-8090}"
python3 "$ROOT/gen/build.py" >/dev/null
rm -rf "$ENV"; mkdir -p "$ENV"
cp -a "$ROOT/dist" "$ENV/public_html"      # website + portal/ + api/ + cms.php
cp -a "$ROOT/crm/ascrm" "$ENV/ascrm"       # CRM application, outside the web root
cd "$ENV"
php -S "127.0.0.1:$PORT" -t public_html "$ROOT/qa/router.php" > "$ENV/php.log" 2>&1 &
SERVER=$!
trap 'kill $SERVER 2>/dev/null || true' EXIT
sleep 1
python3 "$ROOT/qa/crm_e2e.py" "$ENV" "http://127.0.0.1:$PORT" "$ENV/shots"
