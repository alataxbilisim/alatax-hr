#!/usr/bin/env bash
# ALATAX HR — Ubuntu host'ta manuel tam güncelleme (otomatik CI deploy değil)
# Kullanım: cd ~/alatax-hr && bash scripts/deploy-ubuntu-update.sh
set -euo pipefail

BRANCH="${1:-faz4-form-engine}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> git fetch/pull ($BRANCH)"
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"
echo "HEAD: $(git log -1 --oneline)"

echo "==> docker compose up --build"
docker compose up -d --build
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan route:clear

echo "==> frontend pnpm install"
cd frontend
pnpm install

SUNUCU_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
if [[ -n "${SUNUCU_IP:-}" ]]; then
  echo "==> VITE_API_URL kontrol (mevcut .env.local korunur; yoksa yazılır)"
  for app in superadmin company portal; do
    envf="apps/$app/.env.local"
    if [[ ! -f "$envf" ]]; then
      echo "VITE_API_URL=http://${SUNUCU_IP}:8000/api/v1" > "$envf"
      echo "  created $envf"
    else
      echo "  keep $envf ($(grep VITE_API_URL "$envf" || true))"
    fi
  done
fi

echo ""
echo "Tamam. Frontend'i SSH bağımsız başlatın (PC kapanınca ölmesin):"
echo "  bash scripts/ubuntu-frontend-start.sh"
echo "  # durdur: bash scripts/ubuntu-frontend-stop.sh"
echo ""
echo "Doğrulama: curl -s -o /dev/null -w '%{http_code}\\n' http://localhost:8000/up"
echo "Company login: http://${SUNUCU_IP:-SUNUCU_IP}:3002/login  (hard refresh)"
