<?php

declare(strict_types=1);

use App\Models\User;
use Spatie\Permission\Models\Permission;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Permission::findOrCreate('reports.scope.group', 'sanctum');
$user = User::query()->where('email', 'admin-scale-1@example.test')->firstOrFail();
$user->givePermissionTo('reports.scope.group');
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
echo 'ok user='.$user->id.' can='.($user->fresh()->can('reports.scope.group') ? '1' : '0').PHP_EOL;
