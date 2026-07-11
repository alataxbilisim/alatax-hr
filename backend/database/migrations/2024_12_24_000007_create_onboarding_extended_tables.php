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
        // Pre-boarding Portal Token
        Schema::create('preboarding_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('onboarding_process_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            $table->string('token', 64)->unique();
            $table->string('email');
            $table->string('name');
            $table->datetime('expires_at');
            $table->datetime('used_at')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['token', 'is_active']);
        });

        // Onboarding Milestones (30-60-90)
        Schema::create('onboarding_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('onboarding_template_id')->nullable()->constrained()->onDelete('cascade');

            $table->string('name'); // "30 Gün Değerlendirme"
            $table->integer('day_number'); // 30, 60, 90
            $table->text('description')->nullable();
            $table->jsonb('checklist')->nullable(); // Kontrol listesi
            $table->jsonb('evaluation_criteria')->nullable(); // Değerlendirme kriterleri

            $table->boolean('requires_meeting')->default(true);
            $table->boolean('requires_feedback')->default(true);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });

        // Milestone Tamamlama Kayıtları
        Schema::create('milestone_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_process_id')->constrained()->onDelete('cascade');
            $table->foreignId('onboarding_milestone_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Çalışan

            \App\Support\PortableEnum::column($table, 'status', ['pending', 'scheduled', 'completed', 'skipped'], 'pending', false, 64, null);
            $table->date('due_date');
            $table->datetime('completed_at')->nullable();

            // Değerlendirme
            $table->jsonb('checklist_responses')->nullable();
            $table->jsonb('evaluation_scores')->nullable();
            $table->text('employee_feedback')->nullable();
            $table->text('manager_feedback')->nullable();
            $table->integer('overall_rating')->nullable(); // 1-5

            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique(['onboarding_process_id', 'onboarding_milestone_id'], 'milestone_completions_unique');
        });

        // Buddy/Mentor Atamaları
        Schema::create('buddy_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('onboarding_process_id')->constrained()->onDelete('cascade');
            $table->foreignId('new_hire_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('buddy_id')->constrained('users')->onDelete('cascade');

            $table->date('start_date');
            $table->date('end_date')->nullable();
            \App\Support\PortableEnum::column($table, 'status', ['active', 'completed', 'cancelled'], 'active', false, 64, null);
            $table->text('notes')->nullable();

            $table->foreignId('assigned_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['company_id', 'buddy_id']);
        });

        // Buddy Havuzu (Mentor olabilecek kişiler)
        Schema::create('buddy_pool', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->integer('max_mentees')->default(3);
            $table->integer('current_mentees')->default(0);
            $table->jsonb('expertise_areas')->nullable();
            $table->boolean('is_available')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'user_id']);
        });

        // Onboarding NPS Anketi
        Schema::create('onboarding_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('onboarding_process_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            \App\Support\PortableEnum::column($table, 'survey_type', ['week_1', 'week_4', 'month_3', 'exit'], 'month_3', false, 64, null);
            $table->integer('nps_score')->nullable(); // 0-10
            $table->jsonb('responses')->nullable();
            $table->text('additional_comments')->nullable();

            $table->datetime('sent_at')->nullable();
            $table->datetime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['onboarding_process_id', 'survey_type']);
        });

        // Onboarding processes tablosuna yeni alanlar ekle
        Schema::table('onboarding_processes', function (Blueprint $table) {
            $table->boolean('is_preboarding_enabled')->default(false)->after('status');
            $table->datetime('preboarding_started_at')->nullable()->after('is_preboarding_enabled');
            $table->datetime('first_day')->nullable()->after('preboarding_started_at');
            $table->foreignId('buddy_id')->nullable()->after('first_day')->constrained('users')->nullOnDelete();
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_processes', function (Blueprint $table) {
            $table->dropForeign(['buddy_id']);
            $table->dropColumn(['is_preboarding_enabled', 'preboarding_started_at', 'first_day', 'buddy_id']);
        });

        Schema::dropIfExists('onboarding_surveys');
        Schema::dropIfExists('buddy_pool');
        Schema::dropIfExists('buddy_assignments');
        Schema::dropIfExists('milestone_completions');
        Schema::dropIfExists('onboarding_milestones');
        Schema::dropIfExists('preboarding_tokens');
    }
};
