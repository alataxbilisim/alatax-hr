<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tur7 — personel Ad Soyad: portal olmadan da saklanır (users.name sync ayrı).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('full_name', 255)->nullable()->after('employee_code');
        });

        // Mevcut: users.name → full_name (backfill)
        if (Schema::hasColumn('employees', 'full_name')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'pgsql') {
                DB::statement('
                    UPDATE employees e
                    SET full_name = u.name
                    FROM users u
                    WHERE e.user_id = u.id
                      AND e.full_name IS NULL
                      AND u.name IS NOT NULL
                      AND u.name <> \'\'
                ');
            } else {
                DB::statement('
                    UPDATE employees e
                    INNER JOIN users u ON e.user_id = u.id
                    SET e.full_name = u.name
                    WHERE e.full_name IS NULL
                      AND u.name IS NOT NULL
                      AND u.name <> \'\'
                ');
            }
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('full_name');
        });
    }
};
