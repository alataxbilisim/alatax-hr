#!/bin/sh
# Boş volume ilk açılışında (docker-entrypoint-initdb.d) test ve E2E veritabanlarını oluşturur.
# Mevcut volume'lerde çalışmaz; onlar için: docker compose exec app composer test:db
# ve E2E için `cd e2e && pnpm e2e` (hazırlık betiği eksikse oluşturur).
set -eu

for DB in "${POSTGRES_DB}_testing" "${POSTGRES_DB}_e2e"; do
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<-EOSQL
	SELECT 'CREATE DATABASE "${DB}" OWNER "${POSTGRES_USER}"'
	WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = '${DB}')\gexec
EOSQL
echo "[init] veritabanı hazır: ${DB}"
done
