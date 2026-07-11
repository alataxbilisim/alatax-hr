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
        // Performans değerlendirmeleri
        Schema::create('performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('performance_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('users')->cascadeOnDelete(); // Değerlendirilen
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete(); // Değerlendiren
            \App\Support\PortableEnum::column($table, 'status', ['draft', 'submitted', 'approved', 'rejected'], 'draft', false, 64, null);
            $table->decimal('overall_score', 5, 2)->nullable(); // Genel puan
            $table->text('strengths')->nullable(); // Güçlü yönler
            $table->text('improvements')->nullable(); // Gelişim alanları
            $table->text('goals')->nullable(); // Hedefler
            $table->text('reviewer_comments')->nullable();
            $table->text('employee_comments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('performance_reviews');
    }
};
