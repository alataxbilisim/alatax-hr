<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PDKS/Offboarding: process_type + işten çıkış alanları (ekleyici).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onboarding_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('onboarding_templates', 'process_type')) {
                $table->string('process_type', 32)->default('onboarding')->after('company_id');
                $table->index(['company_id', 'process_type']);
            }
        });

        Schema::table('onboarding_processes', function (Blueprint $table) {
            if (! Schema::hasColumn('onboarding_processes', 'process_type')) {
                $table->string('process_type', 32)->default('onboarding')->after('company_id');
                $table->index(['company_id', 'process_type']);
            }
            if (! Schema::hasColumn('onboarding_processes', 'termination_reason_code')) {
                $table->string('termination_reason_code', 10)->nullable()->after('notes');
            }
            if (! Schema::hasColumn('onboarding_processes', 'termination_date')) {
                $table->date('termination_date')->nullable()->after('termination_reason_code');
            }
            if (! Schema::hasColumn('onboarding_processes', 'exit_notes')) {
                $table->text('exit_notes')->nullable()->after('termination_date');
            }
            if (! Schema::hasColumn('onboarding_processes', 'remaining_leave_days')) {
                $table->decimal('remaining_leave_days', 8, 2)->nullable()->after('exit_notes');
            }
            if (! Schema::hasColumn('onboarding_processes', 'employee_id')) {
                $table->foreignId('employee_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('employees')
                    ->nullOnDelete();
            }
        });

        // Mevcut satırlar default onboarding (kolon default zaten koyar; CHECK PostgreSQL)
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                DO \$\$ BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'onboarding_templates_process_type_check'
                    ) THEN
                        ALTER TABLE onboarding_templates
                        ADD CONSTRAINT onboarding_templates_process_type_check
                        CHECK (process_type IN ('onboarding', 'offboarding'));
                    END IF;
                END \$\$;
            ");
            DB::statement("
                DO \$\$ BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'onboarding_processes_process_type_check'
                    ) THEN
                        ALTER TABLE onboarding_processes
                        ADD CONSTRAINT onboarding_processes_process_type_check
                        CHECK (process_type IN ('onboarding', 'offboarding'));
                    END IF;
                END \$\$;
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE onboarding_templates DROP CONSTRAINT IF EXISTS onboarding_templates_process_type_check');
            DB::statement('ALTER TABLE onboarding_processes DROP CONSTRAINT IF EXISTS onboarding_processes_process_type_check');
        }

        Schema::table('onboarding_processes', function (Blueprint $table) {
            if (Schema::hasColumn('onboarding_processes', 'employee_id')) {
                $table->dropConstrainedForeignId('employee_id');
            }
            foreach (['remaining_leave_days', 'exit_notes', 'termination_date', 'termination_reason_code', 'process_type'] as $col) {
                if (Schema::hasColumn('onboarding_processes', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('onboarding_templates', function (Blueprint $table) {
            if (Schema::hasColumn('onboarding_templates', 'process_type')) {
                $table->dropColumn('process_type');
            }
        });
    }
};
