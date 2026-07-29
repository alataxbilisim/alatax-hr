<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2a — KVKK temeli: veri işleme envanteri + aydınlatma + rıza.
 * Bu dalga veri silmez / anonimleştirmez.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_processing_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name');
            $table->jsonb('data_categories')->nullable(); // D1e sensitivity + alan grupları
            $table->text('purpose')->nullable(); // hukuki metin firma doldurur
            $table->string('legal_basis', 64); // explicit_consent|contract|legal_obligation|legitimate_interest|legal_provision
            $table->string('data_subject_group', 32); // employee|candidate|visitor|contractor
            $table->jsonb('recipients')->nullable();
            $table->unsignedInteger('retention_period_months')->nullable();
            $table->boolean('transfer_abroad')->default(false);
            $table->text('transfer_abroad_note')->nullable();
            $table->text('security_measures')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'key']);
            $table->index(['company_id', 'data_subject_group']);
        });

        Schema::create('privacy_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('audience', 32); // employee|candidate|visitor|contractor
            $table->unsignedInteger('version');
            $table->string('title');
            $table->text('body'); // zengin metin — hukuki içerik firma
            $table->timestamp('effective_from')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'audience', 'version']);
            $table->index(['company_id', 'audience', 'is_active']);
        });

        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('subject_type', 32); // employee|candidate|visitor
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('notice_id')->nullable()->constrained('privacy_notices')->nullOnDelete();
            $table->string('consent_type', 64);
            // aydınlatma_okundu | acik_riza_ozel_nitelikli | acik_riza_yurtdisi | ticari_elektronik_ileti | diger
            $table->boolean('granted');
            $table->timestamp('granted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('source', 32); // portal|public_form|admin|import
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->jsonb('evidence')->nullable();
            $table->timestamps();
            // softDeletes YOK — kanıt zinciri; geri çekme withdrawn_at ile

            $table->index(['company_id', 'subject_type', 'subject_id']);
            $table->index(['company_id', 'consent_type', 'granted']);
            $table->index(['company_id', 'notice_id']);
        });

        // legal_basis / audience / consent_type CHECK (string + constraint)
        DB::statement("ALTER TABLE data_processing_activities ADD CONSTRAINT data_processing_activities_legal_basis_check CHECK (legal_basis IN ('explicit_consent','contract','legal_obligation','legitimate_interest','legal_provision'))");
        DB::statement("ALTER TABLE data_processing_activities ADD CONSTRAINT data_processing_activities_subject_group_check CHECK (data_subject_group IN ('employee','candidate','visitor','contractor'))");
        DB::statement("ALTER TABLE privacy_notices ADD CONSTRAINT privacy_notices_audience_check CHECK (audience IN ('employee','candidate','visitor','contractor'))");
        DB::statement("ALTER TABLE consent_records ADD CONSTRAINT consent_records_subject_type_check CHECK (subject_type IN ('employee','candidate','visitor'))");
        DB::statement("ALTER TABLE consent_records ADD CONSTRAINT consent_records_consent_type_check CHECK (consent_type IN ('aydinlatma_okundu','acik_riza_ozel_nitelikli','acik_riza_yurtdisi','ticari_elektronik_ileti','diger'))");
        DB::statement("ALTER TABLE consent_records ADD CONSTRAINT consent_records_source_check CHECK (source IN ('portal','public_form','admin','import'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('consent_records');
        Schema::dropIfExists('privacy_notices');
        Schema::dropIfExists('data_processing_activities');
    }
};
