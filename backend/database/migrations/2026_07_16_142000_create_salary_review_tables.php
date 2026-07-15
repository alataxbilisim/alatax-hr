<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B11 Z3: zam dönemi + satırlar (ekleyici).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_review_periods')) {
            Schema::create('salary_review_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('scope_type', 32)->default('company'); // company|department|branch
                $table->unsignedBigInteger('scope_id')->nullable();
                $table->date('effective_date');
                $table->string('status', 32)->default('draft');
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'status']);
            });
        }

        if (! Schema::hasTable('salary_review_items')) {
            Schema::create('salary_review_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('period_id')->constrained('salary_review_periods')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->decimal('current_amount', 12, 2)->nullable();
                $table->decimal('proposed_amount', 12, 2);
                $table->decimal('increase_percent', 8, 2)->nullable();
                $table->string('currency', 3)->default('TRY');
                $table->string('change_reason', 64)->default('annual_raise');
                $table->text('note')->nullable();
                $table->timestamps();

                $table->unique(['period_id', 'employee_id']);
                $table->index(['company_id', 'period_id']);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                DO \$\$ BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'salary_review_periods_scope_check'
                    ) THEN
                        ALTER TABLE salary_review_periods
                        ADD CONSTRAINT salary_review_periods_scope_check
                        CHECK (scope_type IN ('company', 'department', 'branch'));
                    END IF;
                END \$\$;
            ");
            DB::statement("
                DO \$\$ BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'salary_review_periods_status_check'
                    ) THEN
                        ALTER TABLE salary_review_periods
                        ADD CONSTRAINT salary_review_periods_status_check
                        CHECK (status IN ('draft', 'pending_approval', 'approved', 'rejected', 'cancelled'));
                    END IF;
                END \$\$;
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE salary_review_periods DROP CONSTRAINT IF EXISTS salary_review_periods_scope_check');
            DB::statement('ALTER TABLE salary_review_periods DROP CONSTRAINT IF EXISTS salary_review_periods_status_check');
        }
        Schema::dropIfExists('salary_review_items');
        Schema::dropIfExists('salary_review_periods');
    }
};
