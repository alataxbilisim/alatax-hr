<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D1f — Zamanlanmış rapor + cache TTL kolonları + access log action + ek indeksler.
 * Motor şemasına dokunmaz; yalnız ekleyici.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('saved_reports')->cascadeOnDelete();
            $table->foreignId('dashboard_id')->nullable()->constrained('dashboards')->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('cadence', 16); // daily|weekly|monthly|cron
            $table->unsignedTinyInteger('hour')->default(8);
            $table->unsignedTinyInteger('minute')->default(0);
            $table->unsignedTinyInteger('day')->nullable(); // weekly: 1-7, monthly: 1-28
            $table->string('cron_expression', 64)->nullable();
            $table->string('timezone', 64)->default('Europe/Istanbul');
            $table->string('format', 16)->default('link'); // link|excel|pdf
            $table->jsonb('recipients')->default('[]'); // [{user_id|role_id|department_id}]
            $table->jsonb('filters')->nullable();
            $table->boolean('only_if_data')->default(false);
            $table->boolean('active')->default(true);
            $table->timestampTz('last_run_at')->nullable();
            $table->string('last_status', 32)->nullable(); // success|partial|failed|skipped
            $table->unsignedInteger('failure_count')->default(0);
            $table->timestampTz('next_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'active', 'next_run_at']);
            $table->index(['company_id', 'owner_id']);
        });

        DB::statement("ALTER TABLE report_schedules ADD CONSTRAINT report_schedules_cadence_check CHECK (cadence IN ('daily', 'weekly', 'monthly', 'cron'))");
        DB::statement("ALTER TABLE report_schedules ADD CONSTRAINT report_schedules_format_check CHECK (format IN ('link', 'excel', 'pdf'))");
        DB::statement('ALTER TABLE report_schedules ADD CONSTRAINT report_schedules_target_check CHECK (
            (report_id IS NOT NULL AND dashboard_id IS NULL) OR (report_id IS NULL AND dashboard_id IS NOT NULL)
        )');

        Schema::table('saved_reports', function (Blueprint $table) {
            $table->unsignedInteger('cache_ttl_seconds')->nullable()->after('sort_order');
        });

        Schema::table('dashboards', function (Blueprint $table) {
            $table->unsignedInteger('cache_ttl_seconds')->nullable()->after('is_system');
        });

        // access log: scheduled action
        DB::statement('ALTER TABLE report_access_logs DROP CONSTRAINT IF EXISTS report_access_logs_action_check');
        DB::statement("ALTER TABLE report_access_logs ADD CONSTRAINT report_access_logs_action_check CHECK (action IN ('run', 'preview', 'export', 'drill_details', 'scheduled'))");

        // Sık filtrelenen kolonlar — ekleyici indeksler (drop yok)
        if (! $this->indexExists('employees', 'employees_company_department_status_idx')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->index(['company_id', 'department_id', 'status'], 'employees_company_department_status_idx');
            });
        }
        if (! $this->indexExists('leave_requests', 'leave_requests_company_status_dates_idx')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->index(['company_id', 'status', 'start_date', 'end_date'], 'leave_requests_company_status_dates_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('leave_requests', 'leave_requests_company_status_dates_idx')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropIndex('leave_requests_company_status_dates_idx');
            });
        }
        if ($this->indexExists('employees', 'employees_company_department_status_idx')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropIndex('employees_company_department_status_idx');
            });
        }

        DB::statement('ALTER TABLE report_access_logs DROP CONSTRAINT IF EXISTS report_access_logs_action_check');
        DB::statement("ALTER TABLE report_access_logs ADD CONSTRAINT report_access_logs_action_check CHECK (action IN ('run', 'preview', 'export', 'drill_details'))");

        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropColumn('cache_ttl_seconds');
        });
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->dropColumn('cache_ttl_seconds');
        });

        Schema::dropIfExists('report_schedules');
    }

    private function indexExists(string $table, string $index): bool
    {
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND tablename = ? AND indexname = ?',
            [$table, $index]
        );

        return $row !== null;
    }
};
