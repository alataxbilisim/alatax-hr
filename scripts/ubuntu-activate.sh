#!/usr/bin/env bash
# ALATAX HR — Ubuntu'da git'ten son hali çek + Docker + migrate + frontend (nohup)
# Kullanım: cd ~/alatax-hr && bash scripts/ubuntu-activate.sh [branch]
set -euo pipefail

BRANCH="${1:-faz4-form-engine}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> 1) Git: yerel engelleri temizle + pull ($BRANCH)"
# pull'u engelleyen yerel script değişikliklerini at (sunucu kopyası)
if ! git diff --quiet -- scripts/ 2>/dev/null; then
  echo "    stash: scripts/ yerel farkları"
  git stash push -m "ubuntu-activate auto $(date -Iseconds)" -- scripts/ || true
fi
git fetch origin "$BRANCH"
git checkout "$BRANCH"
git pull origin "$BRANCH"
echo "    HEAD: $(git log -1 --oneline)"

echo "==> 2) Docker up"
docker compose up -d --build
docker compose ps

echo "==> 3) Migrate + cache"
docker compose exec -T app php artisan migrate --force
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan route:clear
docker compose exec -T app php artisan cache:clear || true

echo "==> 4) Frontend bağımlılık + Vite (SSH bağımsız)"
chmod +x scripts/ubuntu-frontend-start.sh scripts/ubuntu-frontend-stop.sh || true
# stop eski süreçler (yoksa start kendi kill eder)
bash scripts/ubuntu-frontend-stop.sh || true
bash scripts/ubuntu-frontend-start.sh

SUNUCU_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
echo ""
echo "========== AKTİF =========="
echo "API         http://${SUNUCU_IP}:8000/up"
echo "SuperAdmin  http://${SUNUCU_IP}:3001"
echo "Company     http://${SUNUCU_IP}:3002"
echo "Portal      http://${SUNUCU_IP}:3003"
echo "Kontrol: curl -s -o /dev/null -w '%{http_code}\\n' http://localhost:8000/up"
echo "         curl -s -o /dev/null -w '%{http_code}\\n' http://localhost:3002/"
echo "==========================="
