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
        Schema::create('payslips', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');

            // Dönem
            $table->string('period'); // YYYY-MM formatında
            $table->integer('year');
            $table->integer('month');

            // Maaş detayları
            $table->decimal('gross_salary', 12, 2)->default(0);
            $table->decimal('net_salary', 12, 2)->default(0);

            // Kesintiler (JSON)
            $table->json('deductions')->nullable();
            // Örnek: {"sgk_employee": 1000, "income_tax": 500, "stamp_tax": 50, "other": []}

            // Ek ödemeler (JSON)
            $table->json('bonuses')->nullable();
            // Örnek: {"overtime": 500, "meal_allowance": 300, "transport": 200, "other": []}

            // Toplam değerler
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('total_bonuses', 12, 2)->default(0);

            // Çalışma detayları
            $table->integer('worked_days')->nullable();
            $table->decimal('overtime_hours', 8, 2)->nullable();

            // PDF dosyası
            $table->string('file_path')->nullable();

            // Yayın durumu
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->onDelete('set null');

            // Personel görüntüleme
            $table->boolean('is_viewed')->default(false);
            $table->timestamp('viewed_at')->nullable();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['employee_id', 'period']);
            $table->index(['company_id', 'period']);
            $table->index(['company_id', 'is_published']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payslips');
    }
};
