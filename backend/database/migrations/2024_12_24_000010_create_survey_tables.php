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
        // Anket Tanımları
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            $table->string('title');
            $table->text('description')->nullable();
            \App\Support\PortableEnum::column($table, 'type', ['engagement', 'satisfaction', 'pulse', 'enps', 'onboarding', 'exit', 'custom'], 'custom', false, 64, null);

            $table->boolean('is_anonymous')->default(true);
            $table->boolean('is_active')->default(true);

            // Zamanlama
            $table->datetime('start_date')->nullable();
            $table->datetime('end_date')->nullable();
            \App\Support\PortableEnum::column($table, 'recurrence', ['none', 'weekly', 'monthly', 'quarterly', 'yearly'], 'none', false, 64, null);

            // Hedef kitle
            \App\Support\PortableEnum::column($table, 'audience', ['all', 'department', 'position', 'custom'], 'all', false, 64, null);
            $table->jsonb('audience_filter')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });

        // Anket Soruları
        Schema::create('survey_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->onDelete('cascade');

            $table->integer('order_number');
            $table->text('question_text');
            \App\Support\PortableEnum::column($table, 'question_type', ['single_choice', 'multiple_choice', 'rating', 'nps', 'text', 'scale', 'matrix'], null, false, 64, null);

            $table->jsonb('options')->nullable(); // Seçenekler
            $table->integer('min_value')->nullable(); // Rating/NPS için
            $table->integer('max_value')->nullable();
            $table->boolean('is_required')->default(true);
            $table->string('category')->nullable(); // Soru kategorisi

            $table->timestamps();
        });

        // Anket Gönderileri
        Schema::create('survey_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null'); // Anonim için null

            $table->string('anonymous_id')->nullable(); // Anonim takip için
            \App\Support\PortableEnum::column($table, 'status', ['started', 'completed', 'abandoned'], 'started', false, 64, null);
            $table->datetime('started_at');
            $table->datetime('completed_at')->nullable();

            // Metadata
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->index(['survey_id', 'status']);
        });

        // Anket Yanıtları
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('survey_submission_id')->constrained()->onDelete('cascade');
            $table->foreignId('survey_question_id')->constrained()->onDelete('cascade');

            $table->text('answer_text')->nullable();
            $table->integer('answer_numeric')->nullable();
            $table->jsonb('answer_array')->nullable(); // Çoklu seçim için

            $table->timestamps();
        });

        // eNPS Kayıtları (Trend takibi için ayrı)
        Schema::create('enps_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('survey_id')->nullable()->constrained()->onDelete('set null');

            $table->date('period_date');
            $table->integer('promoters')->default(0);    // 9-10
            $table->integer('passives')->default(0);     // 7-8
            $table->integer('detractors')->default(0);   // 0-6
            $table->integer('total_responses')->default(0);
            $table->decimal('enps_score', 5, 2)->nullable(); // -100 to +100

            $table->timestamps();

            $table->unique(['company_id', 'period_date']);
        });
        \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enps_records');
        Schema::dropIfExists('survey_responses');
        Schema::dropIfExists('survey_submissions');
        Schema::dropIfExists('survey_questions');
        Schema::dropIfExists('surveys');
    }
};
