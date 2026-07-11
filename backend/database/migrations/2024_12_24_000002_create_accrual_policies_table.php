<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Hakediş politikaları
        Schema::create('accrual_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();

            // Birikim tipi
            \App\Support\PortableEnum::column($table, 'accrual_type', ['annual', 'monthly', 'per_pay_period', 'hourly', 'custom'], 'annual', false, 64, null);

            // Birikim miktarı
            $table->decimal('accrual_rate', 8, 2); // Aylık veya dönemlik miktar
            $table->decimal('max_balance', 8, 2)->nullable(); // Maksimum bakiye limiti
            $table->decimal('min_balance', 8, 2)->default(0); // Negatif bakiye izni

            // Kıdem bazlı artış
            $table->jsonb('tenure_rules')->nullable(); // [{years: 1, days: 14}, {years: 5, days: 18}]

            // Devir kuralları
            $table->boolean('allow_carryover')->default(true);
            $table->decimal('max_carryover_days', 8, 2)->nullable();
            $table->date('carryover_expiry_date')->nullable(); // Devir son kullanma

            // Encashment (Nakde çevirme)
            $table->boolean('allow_encashment')->default(false);
            $table->decimal('max_encashment_days', 8, 2)->nullable();
            $table->decimal('encashment_rate', 8, 2)->default(1); // 1 = tam maaş, 0.5 = yarım

            // Diğer kurallar
            $table->integer('waiting_period_days')->default(0); // İşe başlama sonrası bekleme
            $table->boolean('prorate_first_year')->default(true); // İlk yıl orantılı hesaplama
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'leave_type_id']);
        });

        // Hakediş günlüğü (birikim kayıtları)
        Schema::create('accrual_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('accrual_policy_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('leave_balance_id')->nullable()->constrained()->onDelete('set null');

            \App\Support\PortableEnum::column($table, 'type', ['accrual', 'usage', 'adjustment', 'carryover', 'expiry', 'encashment', 'initial_grant'], null, false, 64, null);

            $table->decimal('amount', 8, 2); // + veya - değer
            $table->decimal('balance_before', 8, 2);
            $table->decimal('balance_after', 8, 2);
            $table->text('description')->nullable();
            $table->date('effective_date');
            $table->morphs('reference'); // leave_request, manual_adjustment vb.

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'leave_type_id']);
            $table->index(['effective_date']);
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accrual_logs');
        Schema::dropIfExists('accrual_policies');
    }
};
