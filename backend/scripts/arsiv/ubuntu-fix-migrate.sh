#!/usr/bin/env bash
set -euo pipefail
cd ~/alatax-hr

echo "==> duplicate positions"
docker compose exec -T postgres psql -U alatax -d alatax_hr <<'SQL'
SELECT company_id, name, count(*) AS c
FROM positions
WHERE deleted_at IS NULL
GROUP BY company_id, name
HAVING count(*) > 1
ORDER BY company_id, name;
SQL

echo "==> soft-delete duplicate positions (keep lowest id, rewire employees)"
docker compose exec -T postgres psql -U alatax -d alatax_hr <<'SQL'
WITH ranked AS (
  SELECT id, company_id, name,
         MIN(id) OVER (PARTITION BY company_id, name) AS keep_id,
         ROW_NUMBER() OVER (PARTITION BY company_id, name ORDER BY id) AS rn
  FROM positions
  WHERE deleted_at IS NULL
)
UPDATE employees e
SET position_id = r.keep_id, updated_at = NOW()
FROM ranked r
WHERE e.position_id = r.id AND r.rn > 1;

WITH ranked AS (
  SELECT id,
         ROW_NUMBER() OVER (PARTITION BY company_id, name ORDER BY id) AS rn
  FROM positions
  WHERE deleted_at IS NULL
)
UPDATE positions p
SET deleted_at = NOW(), updated_at = NOW()
FROM ranked r
WHERE p.id = r.id AND r.rn > 1;
SQL

echo "==> migrate"
docker compose exec -T app php artisan migrate --force

echo "==> config clear"
docker compose exec -T app php artisan config:clear
docker compose exec -T app php artisan route:clear

echo "==> frontend"
bash scripts/ubuntu-frontend-start.sh

echo "==> verify"
curl -s -o /dev/null -w 'api:%{http_code}\n' http://localhost:8000/up
git log -1 --oneline
