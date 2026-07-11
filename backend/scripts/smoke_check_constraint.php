<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

Schema::dropIfExists('faz1_enum_smoke');
Schema::create('faz1_enum_smoke', function (Blueprint $t) {
    $t->id();
    $t->string('status', 32)->default('a');
    $t->check("status IN ('a','b')", 'faz1_enum_smoke_status_check');
});

echo 'create ok ('.DB::connection()->getDriverName().")\n";

try {
    DB::table('faz1_enum_smoke')->insert(['status' => 'c']);
    echo "CHECK DID NOT BLOCK\n";
    exit(1);
} catch (Throwable $e) {
    echo "check blocks bad value OK\n";
}

Schema::dropIfExists('faz1_enum_smoke');
echo "done\n";
