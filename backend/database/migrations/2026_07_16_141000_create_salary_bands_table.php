<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B11 Z2: pozisyon ücret bantları (ekleyici).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salary_bands')) {
            Schema::create('salary_bands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
                $table->decimal('min_amount', 12, 2);
                $table->decimal('mid_amount', 12, 2);
                $table->decimal('max_amount', 12, 2);
                $table->string('currency', 3)->default('TRY');
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'position_id']);
                $table->index(['company_id', 'is_active']);
            });
        }

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("
                DO \$\$ BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'salary_bands_order_check'
                    ) THEN
                        ALTER TABLE salary_bands
                        ADD CONSTRAINT salary_bands_order_check
                        CHECK (min_amount <= mid_amount AND mid_amount <= max_amount);
                    END IF;
                END \$\$;
            ");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE salary_bands DROP CONSTRAINT IF EXISTS salary_bands_order_check');
        }
        Schema::dropIfExists('salary_bands');
    }
};
