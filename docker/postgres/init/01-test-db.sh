#!/bin/sh
# Boş volume ilk açılışında (docker-entrypoint-initdb.d) test veritabanını oluşturur.
# Mevcut volume'lerde çalışmaz; onlar için: docker compose exec app composer test:db
set -eu

TEST_DB="${POSTGRES_DB}_testing"

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname postgres <<-EOSQL
	SELECT 'CREATE DATABASE "${TEST_DB}" OWNER "${POSTGRES_USER}"'
	WHERE NOT EXISTS (SELECT 1 FROM pg_database WHERE datname = '${TEST_DB}')\gexec
EOSQL

echo "[init] test veritabanı hazır: ${TEST_DB}"
