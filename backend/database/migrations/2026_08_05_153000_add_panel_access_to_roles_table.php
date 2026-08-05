<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tur8 — panel erişimi rol adına değil roles.panel_access bayrağına bağlı.
 * employee → false; diğer roller → true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('panel_access')->default(true)->after('guard_name');
        });

        DB::table('roles')->where('name', 'employee')->update(['panel_access' => false]);
        DB::table('roles')->where('name', '!=', 'employee')->update(['panel_access' => true]);
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('panel_access');
        });
    }
};
