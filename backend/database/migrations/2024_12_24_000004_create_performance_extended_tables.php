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
        // OKR - Objectives (Hedefler)
        Schema::create('objectives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('performance_period_id')->nullable()->constrained()->onDelete('set null');

            // Hiyerarşi
            $table->foreignId('parent_id')->nullable()->constrained('objectives')->onDelete('cascade');
            \App\Support\PortableEnum::column($table, 'level', ['company', 'department', 'team', 'individual'], 'individual', false, 64, null);
            $table->foreignId('department_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade'); // Hedef sahibi

            // Hedef bilgileri
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('progress', 5, 2)->default(0); // 0-100
            \App\Support\PortableEnum::column($table, 'status', ['draft', 'active', 'completed', 'cancelled'], 'draft', false, 64, null);
            $table->decimal('weight', 5, 2)->default(100); // Ağırlık

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'owner_id']);
            $table->index(['company_id', 'level']);
        });

        // OKR - Key Results (Anahtar Sonuçlar)
        Schema::create('key_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('objective_id')->constrained()->onDelete('cascade');
            $table->foreignId('owner_id')->constrained('users')->onDelete('cascade');

            $table->string('title');
            $table->text('description')->nullable();
            \App\Support\PortableEnum::column($table, 'metric_type', ['number', 'percentage', 'currency', 'boolean', 'milestone'], 'percentage', false, 64, null);

            $table->decimal('start_value', 15, 2)->default(0);
            $table->decimal('target_value', 15, 2);
            $table->decimal('current_value', 15, 2)->default(0);
            $table->decimal('progress', 5, 2)->default(0);
            $table->decimal('weight', 5, 2)->default(100);

            \App\Support\PortableEnum::column($table, 'status', ['not_started', 'on_track', 'at_risk', 'behind', 'completed'], 'not_started', false, 64, null);
            $table->date('due_date')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['objective_id', 'status']);
        });

        // Key Result güncellemeleri (check-in)
        Schema::create('key_result_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('key_result_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            $table->decimal('previous_value', 15, 2);
            $table->decimal('new_value', 15, 2);
            $table->text('note')->nullable();
            \App\Support\PortableEnum::column($table, 'confidence', ['low', 'medium', 'high'], 'medium', false, 64, null);

            $table->timestamps();
        });

        // 360 Derece Değerlendirme - Geri Bildirim Sağlayıcıları
        Schema::create('feedback_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_review_id')->constrained()->onDelete('cascade');
            $table->foreignId('provider_id')->constrained('users')->onDelete('cascade'); // Geri bildirim veren

            \App\Support\PortableEnum::column($table, 'relationship', ['self', 'manager', 'peer', 'direct_report', 'external'], null, false, 64, null);

            \App\Support\PortableEnum::column($table, 'status', ['pending', 'in_progress', 'submitted', 'declined'], 'pending', false, 64, null);
            $table->datetime('invited_at')->nullable();
            $table->datetime('submitted_at')->nullable();
            $table->datetime('deadline')->nullable();
            $table->text('decline_reason')->nullable();
            $table->boolean('is_anonymous')->default(true);

            $table->timestamps();

            $table->unique(['performance_review_id', 'provider_id']);
        });

        // 360 Derece Geri Bildirim Yanıtları
        Schema::create('feedback_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_provider_id')->constrained()->onDelete('cascade');
            $table->foreignId('performance_criteria_id')->constrained('performance_criteria')->onDelete('cascade');

            $table->integer('score')->nullable(); // 1-5 veya benzeri
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->unique(['feedback_provider_id', 'performance_criteria_id'], 'feedback_response_unique');
        });

        // Sürekli Geri Bildirim (Continuous Feedback)
        Schema::create('continuous_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('from_user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('to_user_id')->constrained('users')->onDelete('cascade');

            \App\Support\PortableEnum::column($table, 'type', ['praise', 'suggestion', 'concern', 'coaching'], 'praise', false, 64, null);

            $table->text('content');
            $table->jsonb('tags')->nullable(); // Etiketler
            $table->boolean('is_public')->default(false); // Herkes görebilir mi?
            $table->boolean('is_anonymous')->default(false);

            // Bağlantılar
            $table->morphs('related'); // Objective, Project, vb.

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'to_user_id']);
        });

        // Yetkinlik Tanımları
        Schema::create('competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // Teknik, Liderlik, İletişim vb.
            $table->jsonb('levels')->nullable(); // Seviye tanımları [{level: 1, name: 'Başlangıç', description: '...'}]
            $table->integer('max_level')->default(5);
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'name']);
        });

        // Pozisyon - Yetkinlik Eşleştirmesi
        Schema::create('position_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('position_name'); // İleride job_positions tablosu ile ilişkilendirilebilir
            $table->foreignId('competency_id')->constrained()->onDelete('cascade');
            $table->integer('expected_level'); // Beklenen seviye
            $table->decimal('weight', 5, 2)->default(100);
            $table->boolean('is_required')->default(true);

            $table->timestamps();

            $table->unique(['company_id', 'position_name', 'competency_id'], 'position_comp_unique');
        });

        // Kullanıcı Yetkinlikleri
        Schema::create('user_competencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('competency_id')->constrained()->onDelete('cascade');

            $table->integer('current_level');
            $table->integer('target_level')->nullable();
            $table->date('assessed_at');
            $table->foreignId('assessed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->unique(['user_id', 'competency_id']);
        });

        // 1-on-1 Görüşmeleri
        Schema::create('one_on_one_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('manager_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('employee_id')->constrained('users')->onDelete('cascade');

            $table->datetime('scheduled_at');
            $table->datetime('completed_at')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->string('location')->nullable();
            $table->string('meeting_link')->nullable(); // Video konferans linki

            \App\Support\PortableEnum::column($table, 'status', ['scheduled', 'completed', 'cancelled', 'rescheduled'], 'scheduled', false, 64, null);
            $table->text('agenda')->nullable();
            $table->text('notes')->nullable(); // Görüşme notları
            $table->jsonb('action_items')->nullable(); // Aksiyon öğeleri
            $table->jsonb('talking_points')->nullable(); // Konuşma noktaları

            \App\Support\PortableEnum::column($table, 'mood', ['very_negative', 'negative', 'neutral', 'positive', 'very_positive'], null, true, 64, null);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'manager_id', 'employee_id']);
        });

        // Performance reviews tablosuna 360 alanları ekle
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->boolean('is_360_enabled')->default(false)->after('status');
            $table->decimal('self_score', 5, 2)->nullable()->after('is_360_enabled');
            $table->decimal('manager_score', 5, 2)->nullable()->after('self_score');
            $table->decimal('peer_score', 5, 2)->nullable()->after('manager_score');
            $table->decimal('report_score', 5, 2)->nullable()->after('peer_score');
            $table->decimal('final_score', 5, 2)->nullable()->after('report_score');
        });
        \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropColumn([
                'is_360_enabled', 'self_score', 'manager_score',
                'peer_score', 'report_score', 'final_score',
            ]);
        });

        Schema::dropIfExists('one_on_one_meetings');
        Schema::dropIfExists('user_competencies');
        Schema::dropIfExists('position_competencies');
        Schema::dropIfExists('competencies');
        Schema::dropIfExists('continuous_feedbacks');
        Schema::dropIfExists('feedback_responses');
        Schema::dropIfExists('feedback_providers');
        Schema::dropIfExists('key_result_updates');
        Schema::dropIfExists('key_results');
        Schema::dropIfExists('objectives');
    }
};
