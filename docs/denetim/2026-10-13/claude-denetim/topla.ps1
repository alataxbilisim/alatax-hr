# Dobedan HR - salt okunur durum toplama betigi (Claude capraz denetim)
# Hicbir veriyi DEGISTIRMEZ. Proje kokunden calistir:
#   powershell -ExecutionPolicy Bypass -File docs\claude-denetim\topla.ps1
$ErrorActionPreference = "Continue"
$root = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
Set-Location $root
$out = Join-Path $PSScriptRoot "cikti"
New-Item -ItemType Directory -Force -Path $out | Out-Null
function Save($name, $block) {
  Write-Host ">> $name"
  try { & $block 2>&1 | Out-File -Encoding utf8 (Join-Path $out $name) }
  catch { $_ | Out-File -Encoding utf8 (Join-Path $out $name) }
}
Save "01-portlar.txt" { Get-NetTCPConnection -State Listen -LocalPort 80,3306,5432,6379,8000,3001,3002,3003,5173 -ErrorAction SilentlyContinue | Select LocalAddress,LocalPort,OwningProcess,@{n="Proc";e={(Get-Process -Id $_.OwningProcess).ProcessName}} | Format-Table -AutoSize | Out-String -Width 300 }
Save "02-docker-ps.txt" { docker compose ps -a }
Save "03-versions.txt" { docker --version; docker compose version; node -v; php -v; git -C $root log -1 --format="%H %ci %s" }
Save "04-artisan-about.txt" { docker compose exec -T app php artisan about }
Save "05-migrate-status.txt" { docker compose exec -T app php artisan migrate:status }
Save "06-routes.json" { docker compose exec -T app php artisan route:list --json }
Save "07-schema.sql" { docker compose exec -T postgres sh -c 'pg_dump -s --no-owner -U "$POSTGRES_USER" "$POSTGRES_DB"' }
$q = "select table_name, (xpath('/row/c/text()', query_to_xml(format('select count(*) as c from %I.%I', table_schema, table_name), false, true, '')))[1]::text::bigint as rows from information_schema.tables where table_schema='public' and table_type='BASE TABLE' order by 1;"
Save "08-row-counts.txt" { $q | docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB"' }
Save "09-pg-meta.txt" { docker compose exec -T postgres sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "select version();" -c "\l" -c "select count(*) tablolar from information_schema.tables where table_schema=''public'' and table_type=''BASE TABLE'';" -c "select pg_size_pretty(pg_database_size(current_database()));"' }
Save "10-db-config.txt" { docker compose exec -T app php artisan tinker --execute="echo config('database.default').' / '.config('database.connections.'.config('database.default').'.host').' / '.config('database.connections.'.config('database.default').'.database');" }
Save "11-tsc.txt" { foreach ($a in "company","portal","superadmin") { "== $a"; Push-Location "frontend\apps\$a"; npx --no-install tsc -p tsconfig.json --noEmit 2>&1 | Select-Object -Last 40; "exit: $LASTEXITCODE"; Pop-Location } }
Save "12-lint.txt" { Push-Location frontend; pnpm lint 2>&1 | Select-Object -Last 60; Pop-Location }
Write-Host "Bitti. Ciktilar: $out"
