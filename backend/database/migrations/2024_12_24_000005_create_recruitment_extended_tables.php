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
        // Mülakat Planlaması
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_position_id')->constrained()->onDelete('cascade');

            $table->string('title'); // "Teknik Mülakat", "HR Görüşmesi"
            \App\Support\PortableEnum::column($table, 'type', ['phone', 'video', 'onsite', 'technical', 'hr', 'panel'], 'onsite', false, 64, null);
            $table->datetime('scheduled_at');
            $table->integer('duration_minutes')->default(60);
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable();

            \App\Support\PortableEnum::column($table, 'status', ['scheduled', 'completed', 'cancelled', 'no_show', 'rescheduled'], 'scheduled', false, 64, null);
            $table->text('notes')->nullable();

            // Değerlendirme
            $table->integer('overall_rating')->nullable(); // 1-5
            \App\Support\PortableEnum::column($table, 'recommendation', ['strong_hire', 'hire', 'no_decision', 'no_hire', 'strong_no_hire'], null, true, 64, null);
            $table->text('feedback')->nullable();

            $table->foreignId('interviewer_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'job_application_id']);
        });

        // Mülakat Değerlendirme Kriterleri
        Schema::create('interview_scorecards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->onDelete('cascade');
            $table->string('criteria_name'); // "Teknik Bilgi", "İletişim"
            $table->integer('score')->nullable(); // 1-5
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // İş Teklifi
        Schema::create('job_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_position_id')->constrained()->onDelete('cascade');

            $table->decimal('salary_offered', 15, 2)->nullable();
            $table->string('currency', 3)->default('TRY');
            $table->date('start_date');
            $table->date('valid_until'); // Teklif geçerlilik

            $table->jsonb('benefits')->nullable(); // Yan haklar
            $table->text('additional_terms')->nullable();
            $table->string('document_path')->nullable(); // Teklif dokümanı

            \App\Support\PortableEnum::column($table, 'status', ['draft', 'sent', 'accepted', 'rejected', 'expired', 'withdrawn'], 'draft', false, 64, null);
            $table->datetime('sent_at')->nullable();
            $table->datetime('responded_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
        });

        // Aday Eşleştirme Skoru (AI-powered)
        Schema::create('candidate_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_application_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_position_id')->constrained()->onDelete('cascade');

            $table->decimal('overall_score', 5, 2); // 0-100
            $table->jsonb('skill_matches')->nullable(); // Yetenek eşleşmeleri
            $table->jsonb('experience_score')->nullable(); // Deneyim puanı
            $table->jsonb('education_score')->nullable(); // Eğitim puanı
            $table->jsonb('keyword_matches')->nullable(); // Anahtar kelime eşleşmeleri

            $table->text('summary')->nullable(); // AI özeti
            $table->timestamps();

            $table->unique(['job_application_id', 'job_position_id']);
        });

        // Recruitment Analytics - Kaynak Takibi
        Schema::create('application_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name'); // LinkedIn, Kariyer.net, Referans
            $table->string('code')->unique();
            \App\Support\PortableEnum::column($table, 'type', ['job_board', 'social', 'referral', 'career_site', 'agency', 'other'], 'other', false, 64, null);
            $table->decimal('cost_per_application', 10, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Job applications tablosuna yeni alanlar ekle
        Schema::table('job_applications', function (Blueprint $table) {
            $table->foreignId('source_id')->nullable()->after('status')->constrained('application_sources')->nullOnDelete();
            $table->decimal('match_score', 5, 2)->nullable()->after('source_id');
            $table->jsonb('parsed_cv_data')->nullable()->after('match_score'); // CV'den çıkarılan veriler
            $table->datetime('last_contacted_at')->nullable()->after('parsed_cv_data');
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropForeign(['source_id']);
            $table->dropColumn(['source_id', 'match_score', 'parsed_cv_data', 'last_contacted_at']);
        });

        Schema::dropIfExists('application_sources');
        Schema::dropIfExists('candidate_scores');
        Schema::dropIfExists('job_offers');
        Schema::dropIfExists('interview_scorecards');
        Schema::dropIfExists('interviews');
    }
};
