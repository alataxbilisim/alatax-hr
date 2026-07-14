<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAZ 4A-1 — Form Engine: form_definitions (layout JSONB).
 * company_id null = sistem varsayılan layout; firma kendi satırıyla override eder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entity_type', 64);
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->jsonb('layout'); // sections → rows → field refs + sort
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'entity_type', 'is_active']);
        });

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX form_definitions_system_entity_unique
                 ON form_definitions (entity_type)
                 WHERE company_id IS NULL AND deleted_at IS NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX form_definitions_company_entity_unique
                 ON form_definitions (company_id, entity_type)
                 WHERE company_id IS NOT NULL AND deleted_at IS NULL'
            );
        } else {
            Schema::table('form_definitions', function (Blueprint $table) {
                $table->unique(['company_id', 'entity_type'], 'form_definitions_company_entity_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('form_definitions');
    }
};
