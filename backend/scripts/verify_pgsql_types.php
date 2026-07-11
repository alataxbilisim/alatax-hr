<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$rows = DB::select("SELECT column_name, udt_name FROM information_schema.columns WHERE table_name='users' AND column_name IN ('type','preferences') ORDER BY 1");
foreach ($rows as $r) {
    echo $r->column_name.'='.$r->udt_name.PHP_EOL;
}
$chk = DB::select("SELECT conname FROM pg_constraint WHERE conname = 'users_type_check'");
echo 'users_type_check='.(count($chk) ? 'yes' : 'no').PHP_EOL;
