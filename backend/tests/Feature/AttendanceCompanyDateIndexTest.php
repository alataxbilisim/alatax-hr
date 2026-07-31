<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * QA-4 — attendance_records (company_id, date) indeksleri.
 */
class AttendanceCompanyDateIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_company_date_indexes_exist_after_migrate(): void
    {
        $this->assertTrue(Schema::hasTable('attendance_records'));

        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pg_indexes yalnızca pgsql');
        }

        foreach ([
            'attendance_records_company_date_idx',
            'attendance_records_company_status_date_idx',
        ] as $index) {
            $row = DB::selectOne(
                'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
                ['attendance_records', $index]
            );
            $this->assertNotNull($row, "Eksik indeks: {$index}");
        }
    }

    public function test_user_date_unique_still_present(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('pg_indexes yalnızca pgsql');
        }

        $row = DB::selectOne(
            "SELECT 1 AS ok FROM pg_indexes
             WHERE schemaname = current_schema()
               AND tablename = 'attendance_records'
               AND indexdef ILIKE '%UNIQUE%'
               AND indexdef ILIKE '%user_id%'
               AND indexdef ILIKE '%date%'"
        );
        $this->assertNotNull($row, 'unique(user_id, date) korunmalı');
    }
}
