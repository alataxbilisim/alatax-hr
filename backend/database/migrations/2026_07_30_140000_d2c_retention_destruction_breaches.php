<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2c — Saklama politikası + imha kuyruğu + hukuki tutma + ihlal defteri.
 * İmha kodu yalnız onaylı adaylar üzerinde çalışır; tarama veriye dokunmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retention_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('data_category', 64);
            $table->string('subject_type', 32);
            $table->string('trigger_event', 32);
            $table->unsignedInteger('retention_months');
            $table->string('strategy', 32)->default('anonymize');
            $table->text('legal_basis_note')->nullable();
            $table->boolean('active')->default(false); // seed asla otomatik aktif değil
            $table->boolean('requires_approval')->default(true);
            $table->boolean('is_system_draft')->default(false);
            $table->string('name');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'active']);
            $table->index(['company_id', 'data_category']);
        });

        Schema::create('legal_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->text('reason');
            $table->string('case_reference')->nullable();
            $table->foreignId('placed_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('placed_at');
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'subject_type', 'subject_id', 'active']);
        });

        // Append-only karar kaydı — denetimde "neden silinmedi/silindi"
        Schema::create('retention_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destruction_candidate_id')->nullable();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('decision', 32); // destroy|defer|exclude
            $table->text('reason');
            $table->date('defer_until')->nullable();
            $table->foreignId('decided_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at');

            $table->index(['company_id', 'subject_type', 'subject_id']);
        });

        Schema::create('destruction_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('retention_policy_id')->nullable()->constrained('retention_policies')->nullOnDelete();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('data_category', 64);
            $table->unsignedInteger('record_count')->default(0);
            $table->timestamp('due_since')->nullable();
            $table->string('status', 32)->default('pending');
            // pending|deferred|excluded|skipped_legal_hold|approved|processing|completed|failed
            $table->string('strategy', 32)->default('anonymize');
            $table->jsonb('preview_snapshot')->nullable();
            $table->foreignId('approval_id')->nullable();
            $table->text('skip_reason')->nullable();
            $table->timestamp('deferred_until')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'subject_type', 'subject_id']);
        });

        Schema::table('retention_decisions', function (Blueprint $table) {
            $table->foreign('destruction_candidate_id')
                ->references('id')->on('destruction_candidates')->nullOnDelete();
        });

        Schema::create('destruction_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('approved_at');
            $table->jsonb('candidate_ids');
            $table->boolean('dry_run_confirmed')->default(false);
            $table->string('status', 32)->default('approved'); // approved|processing|completed|failed
            $table->text('note')->nullable();
            $table->timestamps();
        });

        Schema::table('destruction_candidates', function (Blueprint $table) {
            $table->foreign('approval_id')
                ->references('id')->on('destruction_approvals')->nullOnDelete();
        });

        // Append-only imha tutanağı — ASLA silinmez/güncellenmez
        Schema::create('destruction_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('destruction_candidate_id')->nullable()->constrained('destruction_candidates')->nullOnDelete();
            $table->foreignId('approval_id')->nullable()->constrained('destruction_approvals')->nullOnDelete();
            $table->foreignId('retention_policy_id')->nullable()->constrained('retention_policies')->nullOnDelete();
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->string('data_category', 64)->nullable();
            $table->string('strategy', 32);
            $table->string('collector_key', 64)->nullable();
            $table->unsignedInteger('rows_affected')->default(0);
            $table->jsonb('summary')->nullable();
            $table->string('content_hash', 64)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('dry_run')->default(false);
            $table->string('outcome', 32)->default('success'); // success|failed|skipped
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->index(['company_id', 'created_at']);
        });

        Schema::create('data_breaches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->timestamp('detected_at');
            $table->timestamp('occurred_at')->nullable();
            $table->text('description');
            $table->jsonb('affected_categories')->nullable();
            $table->unsignedInteger('affected_subject_count')->default(0);
            $table->string('severity', 32)->default('medium');
            $table->text('root_cause')->nullable();
            $table->text('containment_actions')->nullable();
            $table->boolean('notified_kvkk')->default(false);
            $table->timestamp('notified_kvkk_at')->nullable();
            $table->boolean('notified_subjects')->default(false);
            $table->timestamp('notified_subjects_at')->nullable();
            $table->string('notified_subjects_method')->nullable();
            $table->string('status', 32)->default('open'); // open|investigating|contained|closed
            $table->timestamp('closed_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'detected_at']);
        });

        // CHECK constraints (PostgreSQL)
        DB::statement("ALTER TABLE retention_policies ADD CONSTRAINT retention_policies_strategy_check CHECK (strategy IN ('anonymize','pseudonymize','hard_delete','archive'))");
        DB::statement("ALTER TABLE retention_policies ADD CONSTRAINT retention_policies_trigger_check CHECK (trigger_event IN ('ise_giris','isten_ayrilma','basvuru_reddi','kayit_tarihi','belge_tarihi','son_islem_tarihi'))");
        DB::statement("ALTER TABLE retention_decisions ADD CONSTRAINT retention_decisions_decision_check CHECK (decision IN ('destroy','defer','exclude'))");
        DB::statement("ALTER TABLE destruction_candidates ADD CONSTRAINT destruction_candidates_status_check CHECK (status IN ('pending','deferred','excluded','skipped_legal_hold','approved','processing','completed','failed'))");
        DB::statement("ALTER TABLE data_breaches ADD CONSTRAINT data_breaches_severity_check CHECK (severity IN ('low','medium','high','critical'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('data_breaches');
        Schema::dropIfExists('destruction_logs');
        Schema::table('destruction_candidates', function (Blueprint $table) {
            $table->dropForeign(['approval_id']);
        });
        Schema::dropIfExists('destruction_approvals');
        Schema::table('retention_decisions', function (Blueprint $table) {
            $table->dropForeign(['destruction_candidate_id']);
        });
        Schema::dropIfExists('destruction_candidates');
        Schema::dropIfExists('retention_decisions');
        Schema::dropIfExists('legal_holds');
        Schema::dropIfExists('retention_policies');
    }
};
