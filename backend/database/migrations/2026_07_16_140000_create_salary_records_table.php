<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B11 Z1: efektif tarihli ücret geçmişi (ekleyici).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_records')) {
            Schema::create('salary_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('effective_date');
                $table->decimal('amount', 12, 2);
                $table->string('currency', 3)->default('TRY');
                $table->string('change_reason', 64);
                $table->text('note')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['company_id', 'employee_id', 'effective_date']);
                $table->index(['company_id', 'change_reason']);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                DO \$\$ BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'salary_records_amount_positive'
                    ) THEN
                        ALTER TABLE salary_records
                        ADD CONSTRAINT salary_records_amount_positive CHECK (amount >= 0);
                    END IF;
                END \$\$;
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE salary_records DROP CONSTRAINT IF EXISTS salary_records_amount_positive');
        }
        Schema::dropIfExists('salary_records');
    }
};
