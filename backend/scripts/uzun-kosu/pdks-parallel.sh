#!/usr/bin/env bash
# Gerçek eşzamanlı punch: N worker process × AttendanceClockService
# docker exec -e DB_DATABASE=alatax_hr_testing alatax-hr-app bash scripts/uzun-kosu/pdks-parallel.sh
set -euo pipefail
cd /var/www/html
export DB_DATABASE=alatax_hr_testing
N="${1:-500}"
WORKERS="${2:-50}"
OUT="scripts/uzun-kosu/pdks-parallel-results.txt"
: > "$OUT"
php -r '
require "vendor/autoload.php";
$app=require "bootstrap/app.php";
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cid=DB::table("companies")->where("slug","scale-co-1")->value("id");
$uids=DB::table("users")->where("home_company_id",$cid)->where("email","like","scale-e%")->orderBy("id")->limit('"$N"')->pluck("id");
file_put_contents("scripts/uzun-kosu/pdks-uids.json", $uids->values()->toJson());
$today=now()->toDateString();
DB::table("attendance_records")->whereIn("user_id",$uids)->whereDate("date",$today)->delete();
echo "uids=".$uids->count()."\n";
'
mapfile -t UIDS < <(php -r 'foreach(json_decode(file_get_contents("scripts/uzun-kosu/pdks-uids.json")) as $id) echo $id, PHP_EOL;')
echo "parallel workers=$WORKERS n=${#UIDS[@]}"
start=$(date +%s%3N)
printf '%s\n' "${UIDS[@]}" | xargs -P "$WORKERS" -I{} php scripts/uzun-kosu/pdks-one.php {} >> "$OUT"
end=$(date +%s%3N)
echo "WALL_MS=$((end-start))"
php scripts/uzun-kosu/pdks-parallel-summarize.php
