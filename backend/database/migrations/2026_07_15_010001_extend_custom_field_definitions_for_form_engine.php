<?php

use App\Support\PortableEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAZ 4A-1 — Form Engine: custom_field_definitions genişletme.
 * Sistem alanları company_id=null (Lookup deseni); firma override kendi company_id ile.
 * Mevcut kolonlara dokunulmaz; yalnızca eklenir + company_id nullable yapılır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $this->dropForeignKeyIfExists('custom_field_definitions', 'company_id');
        });

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            // İsim bazlı unique (pgsql/mysql)
            try {
                $table->dropUnique('custom_field_definitions_company_id_entity_type_field_key_unique');
            } catch (\Throwable) {
                // sqlite / alternatif isim
                try {
                    $table->dropUnique(['company_id', 'entity_type', 'field_key']);
                } catch (\Throwable) {
                    // yok
                }
            }
        });

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
        });

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->nullOnDelete();

            $table->boolean('is_system')->default(false)->after('entity_type');
            $table->string('system_key', 100)->nullable()->after('is_system');
            $table->string('label_override')->nullable()->after('field_label');
            $table->boolean('is_hidden')->default(false)->after('is_active');
            $table->boolean('is_required_override')->nullable()->after('is_required');
            PortableEnum::column(
                $table,
                'field_permission',
                ['readonly', 'hidden'],
                null,
                true,
                32,
                'is_required_override'
            );

            $table->index(['entity_type', 'is_system', 'system_key'], 'cfd_entity_system_key_idx');
        });

        PortableEnum::flushChecks();

        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement(
                'CREATE UNIQUE INDEX cfd_system_entity_field_key_unique
                 ON custom_field_definitions (entity_type, field_key)
                 WHERE company_id IS NULL AND deleted_at IS NULL'
            );
            DB::statement(
                'CREATE UNIQUE INDEX cfd_company_entity_field_key_unique
                 ON custom_field_definitions (company_id, entity_type, field_key)
                 WHERE company_id IS NOT NULL AND deleted_at IS NULL'
            );
        } else {
            Schema::table('custom_field_definitions', function (Blueprint $table) {
                $table->unique(
                    ['company_id', 'entity_type', 'field_key'],
                    'cfd_company_entity_field_key_unique'
                );
            });
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS cfd_system_entity_field_key_unique');
            DB::statement('DROP INDEX IF EXISTS cfd_company_entity_field_key_unique');
        } else {
            Schema::table('custom_field_definitions', function (Blueprint $table) {
                try {
                    $table->dropUnique('cfd_company_entity_field_key_unique');
                } catch (\Throwable) {
                    // yok
                }
            });
        }

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->dropIndex('cfd_entity_system_key_idx');
            $table->dropColumn([
                'is_system',
                'system_key',
                'label_override',
                'is_hidden',
                'is_required_override',
                'field_permission',
            ]);
        });

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $this->dropForeignKeyIfExists('custom_field_definitions', 'company_id');
        });

        Schema::table('custom_field_definitions', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable(false)->change();
            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(
                ['company_id', 'entity_type', 'field_key'],
                'custom_field_definitions_company_id_entity_type_field_key_unique'
            );
        });
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        $sm = Schema::getConnection()->getSchemaBuilder();
        $foreignKeys = method_exists($sm, 'getForeignKeys')
            ? $sm->getForeignKeys($table)
            : [];

        foreach ($foreignKeys as $foreign) {
            $cols = $foreign['columns'] ?? [];
            if (in_array($column, $cols, true)) {
                Schema::table($table, function (Blueprint $blueprint) use ($foreign) {
                    $blueprint->dropForeign($foreign['name']);
                });

                return;
            }
        }

        // Fallback: Laravel varsayılan adı
        try {
            Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                $blueprint->dropForeign([$column]);
            });
        } catch (\Throwable) {
            try {
                Schema::table($table, function (Blueprint $blueprint) use ($table, $column) {
                    $blueprint->dropForeign("{$table}_{$column}_foreign");
                });
            } catch (\Throwable) {
                // yok
            }
        }
    }
};
