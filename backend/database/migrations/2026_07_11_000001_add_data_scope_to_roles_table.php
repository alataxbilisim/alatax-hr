<?php

use App\Enums\DataScopeLevel;
use App\Support\PortableEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // null = config/data-scope.php varsayılanı kullan
            PortableEnum::column(
                $table,
                'data_scope',
                DataScopeLevel::values(),
                null,
                true,
                32,
                'guard_name',
            );
        });

        PortableEnum::flushChecks();
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('data_scope');
        });
    }
};
