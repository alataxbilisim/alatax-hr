<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * TEMEL SAĞLAMLAŞTIRMA §5 — Soft-delete-aware partial unique indexes.
 *
 * Soft-deleted rows must not block re-creation of the same code/email/etc.
 * Old absolute unique constraints are replaced with PostgreSQL partial unique
 * indexes (WHERE deleted_at IS NULL).
 *
 * Also adds (company_id, national_id) partial unique (§4 requirement).
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- employees (company_id, employee_code) ---
        // Drop old absolute unique
        $this->dropIndexSafe('employees', 'employees_company_id_employee_code_unique');
        DB::statement(
            'CREATE UNIQUE INDEX employees_company_employee_code_active_unique
             ON employees (company_id, employee_code)
             WHERE deleted_at IS NULL'
        );

        // --- employees (company_id, national_id) ---
        $dupes = DB::select("
            SELECT national_id, company_id, count(*) AS cnt
            FROM employees
            WHERE deleted_at IS NULL AND national_id IS NOT NULL AND national_id <> ''
            GROUP BY company_id, national_id
            HAVING count(*) > 1
        ");
        if (! empty($dupes)) {
            foreach ($dupes as $d) {
                Log::warning('§5: duplicate national_id before unique index', [
                    'company_id' => $d->company_id,
                    'national_id' => $d->national_id,
                    'count' => $d->cnt,
                ]);
            }
        }
        DB::statement(
            "CREATE UNIQUE INDEX employees_company_national_id_active_unique
             ON employees (company_id, national_id)
             WHERE deleted_at IS NULL AND national_id IS NOT NULL AND national_id <> ''"
        );

        // --- users (email) ---
        $this->dropIndexSafe('users', 'users_email_unique');
        DB::statement(
            'CREATE UNIQUE INDEX users_email_active_unique
             ON users (email)
             WHERE deleted_at IS NULL'
        );

        // --- branches (company_id, code) ---
        $this->dropIndexSafe('branches', 'branches_company_code_unique');
        DB::statement(
            'CREATE UNIQUE INDEX branches_company_code_active_unique
             ON branches (company_id, code)
             WHERE deleted_at IS NULL'
        );

        // --- positions (company_id, code) ---
        $this->dropIndexSafe('positions', 'positions_company_id_code_unique');
        DB::statement(
            'CREATE UNIQUE INDEX positions_company_code_active_unique
             ON positions (company_id, code)
             WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        // Restore old absolute unique constraints
        DB::statement('DROP INDEX IF EXISTS employees_company_employee_code_active_unique');
        DB::statement('DROP INDEX IF EXISTS employees_company_national_id_active_unique');
        DB::statement('DROP INDEX IF EXISTS users_email_active_unique');
        DB::statement('DROP INDEX IF EXISTS branches_company_code_active_unique');
        DB::statement('DROP INDEX IF EXISTS positions_company_code_active_unique');

        Schema::table('employees', function ($table) {
            $table->unique(['company_id', 'employee_code']);
        });
        Schema::table('users', function ($table) {
            $table->unique('email');
        });
        Schema::table('branches', function ($table) {
            $table->unique(['company_id', 'code'], 'branches_company_code_unique');
        });
        Schema::table('positions', function ($table) {
            $table->unique(['company_id', 'code']);
        });
    }

    private function dropIndexSafe(string $table, string $index): void
    {
        // PostgreSQL unique constraints must be dropped via ALTER TABLE
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$index}");
    }
};
