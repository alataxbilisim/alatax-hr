<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TEMEL SAĞLAMLAŞTIRMA §4
 *
 * 1. Drop employees.position (string) — position_id is now SSOT.
 * 2. Add partial unique on positions (company_id, name) WHERE deleted_at IS NULL
 *    to prevent duplicate position names within the same company.
 *
 * KARAR: positions (company_id, name) unique index eklendi çünkü "Genel Müdür"
 * gibi isimlerin farklı code'larla tekrarlanması veri tutarsızlığına yol açıyor.
 * Code zaten unique (PositionCatalogSeedService); name de unique olmalı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('position');
        });

        // Partial unique: aynı firmada silinmemiş iki pozisyon aynı ada sahip olamaz
        DB::statement(
            'CREATE UNIQUE INDEX positions_company_name_active_unique
             ON positions (company_id, name)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS positions_company_name_active_unique');

        Schema::table('employees', function (Blueprint $table) {
            $table->string('position', 100)->nullable()->after('title');
        });
    }
};
