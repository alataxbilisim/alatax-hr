#!/usr/bin/env bash
# Gerçek HTTP paralel QR punch — scale kullanıcılarıyla (alatax_hr_testing API).
# Kullanım: APP URL + önceden üretilmiş token listesi gerekir.
# Bu script ölçüm turunda token/kiosk akışı yoksa atlanır; service-level pdks-concurrency.php birincil.

set -euo pipefail
BASE="${BASE_URL:-http://localhost:8000/api/v1}"
TOKEN_FILE="${1:-/tmp/pdks-tokens.txt}"
OUT="${2:-/tmp/pdks-http-results.txt}"
: > "$OUT"
if [[ ! -f "$TOKEN_FILE" ]]; then
  echo "SKIP: no token file $TOKEN_FILE"
  exit 0
fi

# token_file lines: bearer|qr_token
n=0
while IFS='|' read -r bearer qr; do
  n=$((n+1))
  (
    start=$(date +%s%3N)
    code=$(curl -s -o /tmp/pdks-$n.json -w "%{http_code}" -X POST "$BASE/portal/attendance/qr-scan" \
      -H "Authorization: Bearer $bearer" -H "Content-Type: application/json" \
      -H "Accept: application/json" \
      -d "{\"token\":\"$qr\"}" || echo 000)
    end=$(date +%s%3N)
    echo "$n $code $((end-start))" >> "$OUT"
  ) &
  if (( n % 50 == 0 )); then wait; fi
done < "$TOKEN_FILE"
wait
echo "DONE lines=$(wc -l < "$OUT")"
awk '{c[$2]++; t+=$3; n++} END{print "http_codes"; for (k in c) print k,c[k]; print "avg_ms", (n?t/n:0)}' "$OUT"
