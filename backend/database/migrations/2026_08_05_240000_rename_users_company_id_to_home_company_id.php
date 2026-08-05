<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.company_id = portal/ev şirketi (home), panel aktif şirket değil.
 * Kolon adıyla yanlış kullanımı engelle: home_company_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'company_id')) {
            return;
        }

        // FK/index adlarını PostgreSQL'de birlikte taşı
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('company_id', 'home_company_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('home_company_id')
                ->references('id')
                ->on('companies')
                ->nullOnDelete();
        });

        // Eski index adı kalmış olabilir
        $indexes = collect(DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'users' AND indexdef LIKE '%company_id%'"
        ))->pluck('indexname');

        foreach ($indexes as $indexName) {
            if (str_contains((string) $indexName, 'home_company_id')) {
                continue;
            }
            if (str_contains((string) $indexName, 'company_id') && ! str_contains((string) $indexName, 'last_company')) {
                DB::statement('ALTER INDEX IF EXISTS '.$indexName.' RENAME TO users_home_company_id_index');
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'home_company_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['home_company_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('home_company_id', 'company_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->nullOnDelete();
        });
    }
};
