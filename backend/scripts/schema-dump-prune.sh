#!/usr/bin/env bash
# pg_dump app konteynerinde 15.x; Postgres 16.x — dump postgres konteynerinden alınır.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SCHEMA_DIR="${ROOT}/database/schema"
SCHEMA_FILE="${SCHEMA_DIR}/pgsql-schema.sql"
TMP_SCHEMA="$(mktemp)"
TMP_MIG="$(mktemp)"

mkdir -p "${SCHEMA_DIR}"

# Kimlik bilgisi betikte tutulmaz: kullanıcı/şifre postgres konteynerinin kendi ortamından
# (POSTGRES_USER / POSTGRES_PASSWORD) okunur. Kaynak DB: DB_NAME (varsayılan dobedan_hr).
DB_NAME="${DB_NAME:-dobedan_hr}"
PG_DUMP_IN_CONTAINER='PGPASSWORD="$POSTGRES_PASSWORD" pg_dump --no-owner --no-acl -U "$POSTGRES_USER" "$@"'

COMPOSE=(docker compose -f "${ROOT}/../docker-compose.yml")

echo "==> schema-only dump (postgres container)"
"${COMPOSE[@]}" exec -T postgres sh -c "${PG_DUMP_IN_CONTAINER}" pg_dump \
  --schema-only -d "${DB_NAME}" > "${TMP_SCHEMA}"

echo "==> migrations data dump"
"${COMPOSE[@]}" exec -T postgres sh -c "${PG_DUMP_IN_CONTAINER}" pg_dump \
  --data-only -t public.migrations -d "${DB_NAME}" > "${TMP_MIG}"

# \restrict / \unrestrict satırları bazı psql istemcilerinde sorun çıkarabilir; temizle
# (Laravel load app konteynerindeki psql 15 kullanır)
{
  grep -vE '^\\restrict |^\\unrestrict ' "${TMP_SCHEMA}" || true
  echo
  grep -vE '^\\restrict |^\\unrestrict |^--|^SET |^SELECT pg_catalog|^$' "${TMP_MIG}" || true
} > "${SCHEMA_FILE}"

# Daha güvenli: schema + migrations data'yı birleştir, restrict satırlarını çıkar
{
  grep -vE '^\\restrict |^\\unrestrict ' "${TMP_SCHEMA}"
  echo
  grep -vE '^\\restrict |^\\unrestrict ' "${TMP_MIG}"
} > "${SCHEMA_FILE}"

rm -f "${TMP_SCHEMA}" "${TMP_MIG}"

BYTES=$(wc -c < "${SCHEMA_FILE}" | tr -d ' ')
TZ_WITH=$(grep -c 'with time zone' "${SCHEMA_FILE}" || true)
TZ_WITHOUT=$(grep -c 'without time zone' "${SCHEMA_FILE}" || true)
MIG_COUNT=$(grep -c $'\t[0-9]\\{4\\}_' "${SCHEMA_FILE}" || true)

echo "SCHEMA_BYTES=${BYTES}"
echo "TZ_WITH=${TZ_WITH}"
echo "TZ_WITHOUT=${TZ_WITHOUT}"
echo "MIGRATION_ROWS_APPROX=${MIG_COUNT}"

echo "==> prune migration PHP files listed in dump"
# migrations tablosundaki tüm dosyaları sil (baseline'a gömüldü)
php -r '
$schema = file_get_contents("database/schema/pgsql-schema.sql");
preg_match_all("/^\d+\t([^\t]+)\t\d+$/m", $schema, $m);
$names = $m[1] ?? [];
$dir = "database/migrations";
$deleted = 0;
$missing = 0;
foreach ($names as $name) {
    $path = $dir . "/" . $name . ".php";
    if (is_file($path)) {
        unlink($path);
        $deleted++;
    } else {
        $missing++;
    }
}
$remaining = glob($dir . "/*.php");
echo "PRUNED={$deleted}\n";
echo "MISSING_FILES={$missing}\n";
echo "REMAINING=" . count($remaining ?: []) . "\n";
foreach ($remaining ?: [] as $f) {
    echo "LEFT:" . basename($f) . "\n";
}
'

echo "==> done"
