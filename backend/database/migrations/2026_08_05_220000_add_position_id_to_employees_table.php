<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faz6 öncesi — employees.position_id FK (string position kolonu korunur, dual-write).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('position_id')
                ->nullable()
                ->after('position')
                ->constrained('positions')
                ->nullOnDelete();
            $table->index(['company_id', 'position_id']);
        });

        // Tek eşleşen ad → position_id (belirsiz / eşleşmeyen bırakılır)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                UPDATE employees e
                SET position_id = p.id
                FROM positions p
                WHERE e.position_id IS NULL
                  AND e.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND e.company_id = p.company_id
                  AND e.position IS NOT NULL
                  AND e.position <> ''
                  AND p.name = e.position
                  AND (
                    SELECT COUNT(*) FROM positions p2
                    WHERE p2.company_id = e.company_id
                      AND p2.deleted_at IS NULL
                      AND p2.name = e.position
                  ) = 1
            ");
            // Code eşleşmesi (Tur8 sonrası kayıtlar)
            DB::statement("
                UPDATE employees e
                SET position_id = p.id
                FROM positions p
                WHERE e.position_id IS NULL
                  AND e.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND e.company_id = p.company_id
                  AND e.position IS NOT NULL
                  AND e.position <> ''
                  AND p.code = e.position
            ");
        } else {
            DB::statement("
                UPDATE employees e
                INNER JOIN positions p ON p.company_id = e.company_id
                    AND p.deleted_at IS NULL
                    AND p.name = e.position
                SET e.position_id = p.id
                WHERE e.position_id IS NULL
                  AND e.deleted_at IS NULL
                  AND e.position IS NOT NULL
                  AND e.position <> ''
                  AND (
                    SELECT COUNT(*) FROM positions p2
                    WHERE p2.company_id = e.company_id
                      AND p2.deleted_at IS NULL
                      AND p2.name = e.position
                  ) = 1
            ");
            DB::statement("
                UPDATE employees e
                INNER JOIN positions p ON p.company_id = e.company_id
                    AND p.deleted_at IS NULL
                    AND p.code = e.position
                SET e.position_id = p.id
                WHERE e.position_id IS NULL
                  AND e.deleted_at IS NULL
                  AND e.position IS NOT NULL
                  AND e.position <> ''
            ");
        }
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('position_id');
        });
    }
};
