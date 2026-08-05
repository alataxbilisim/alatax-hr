#!/usr/bin/env bash
set -euo pipefail
LABEL="${1:-fresh}"
START=$(date +%s)
php artisan migrate:fresh --seed --force
END=$(date +%s)
ELAPSED=$((END - START))
echo "FRESH_${LABEL}_SEC=${ELAPSED}"
