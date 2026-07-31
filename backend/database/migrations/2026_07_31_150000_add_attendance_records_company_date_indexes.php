<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * QA-4 / G0 öneri #10 — grup raporları için company+date filtre indeksleri.
 * unique(user_id, date) değiştirilmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_records')) {
            return;
        }

        Schema::table('attendance_records', function (Blueprint $table) {
            if (! $this->indexExists('attendance_records', 'attendance_records_company_date_idx')) {
                $table->index(['company_id', 'date'], 'attendance_records_company_date_idx');
            }
            if (! $this->indexExists('attendance_records', 'attendance_records_company_status_date_idx')) {
                $table->index(['company_id', 'status', 'date'], 'attendance_records_company_status_date_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_records')) {
            return;
        }

        Schema::table('attendance_records', function (Blueprint $table) {
            if ($this->indexExists('attendance_records', 'attendance_records_company_status_date_idx')) {
                $table->dropIndex('attendance_records_company_status_date_idx');
            }
            if ($this->indexExists('attendance_records', 'attendance_records_company_date_idx')) {
                $table->dropIndex('attendance_records_company_date_idx');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return Schema::hasIndex($table, $index);
        }

        $row = DB::selectOne(
            'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
            [$table, $index]
        );

        return $row !== null;
    }
};
