<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 4 — Lookup Engine çekirdek tablosu.
 * company_id null = sistem / platform default; firma override kendi company_id ile.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lookups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lookup_type', 64);
            $table->string('value', 100); // K-A: kayıtların tuttuğu sabit referans
            $table->string('label');
            $table->string('color', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false); // salt okunur sistem lookup
            $table->foreignId('parent_lookup_id')->nullable()->constrained('lookups')->nullOnDelete(); // K-C
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'lookup_type', 'is_active']);
            $table->index(['lookup_type', 'value']);
        });

        // unique (company_id, lookup_type, value) — NULL company_id için partial index
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX lookups_system_type_value_unique ON lookups (lookup_type, value) WHERE company_id IS NULL AND deleted_at IS NULL');
            DB::statement('CREATE UNIQUE INDEX lookups_company_type_value_unique ON lookups (company_id, lookup_type, value) WHERE company_id IS NOT NULL AND deleted_at IS NULL');
        } else {
            // sqlite / diğer: basit unique (NULL'lar ayrı satır olabilir — seed tekil tutulur)
            Schema::table('lookups', function (Blueprint $table) {
                $table->unique(['company_id', 'lookup_type', 'value'], 'lookups_company_type_value_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lookups');
    }
};
