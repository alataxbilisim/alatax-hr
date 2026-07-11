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
        // Onay iş akışı tanımları
        Schema::create('approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name'); // Örn: "Yıllık İzin Onay Akışı"
            $table->string('entity_type'); // leave_request, asset_request, expense_request, etc.
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false); // Varsayılan akış mı?
            $table->jsonb('conditions')->nullable(); // Koşullar: gün sayısı, tutar vb.
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'entity_type']);
        });

        // Onay adımları
        Schema::create('approval_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->onDelete('cascade');
            $table->integer('step_order'); // Adım sırası
            $table->string('name'); // Adım adı
            \App\Support\PortableEnum::column($table, 'approver_type', ['direct_manager', 'department_head', 'specific_user', 'specific_role', 'hr', 'cfo', 'ceo'], null, false, 64, null);
            $table->foreignId('specific_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('specific_role')->nullable(); // Spatie rol adı
            $table->boolean('is_required')->default(true); // Zorunlu mu?
            $table->boolean('can_skip')->default(false); // Atlanabilir mi?
            $table->integer('timeout_hours')->nullable(); // Otomatik escalation süresi
            \App\Support\PortableEnum::column($table, 'timeout_action', ['escalate', 'auto_approve', 'auto_reject'], null, true, 64, null);
            $table->timestamps();

            $table->index(['approval_workflow_id', 'step_order']);
        });

        // Onay kayıtları (her talep için)
        Schema::create('approval_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('approval_workflow_id')->constrained()->onDelete('cascade');
            $table->foreignId('approval_step_id')->constrained()->onDelete('cascade');
            $table->morphs('approvable'); // leave_request, asset_request vb.
            $table->foreignId('approver_id')->nullable()->constrained('users')->onDelete('set null');
            \App\Support\PortableEnum::column($table, 'status', ['pending', 'approved', 'rejected', 'skipped', 'escalated'], 'pending', false, 64, null);
            $table->text('comment')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->integer('step_order');
            $table->boolean('is_current')->default(false); // Şu an bu adımda mı?
            $table->timestamp('escalated_at')->nullable();
            $table->foreignId('escalated_to')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // morphs() zaten approvable_type ve approvable_id için index oluşturuyor
            $table->index(['company_id', 'status']);
        });

        // Vekalet yönetimi
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('delegator_id')->constrained('users')->onDelete('cascade'); // Vekalet veren
            $table->foreignId('delegate_id')->constrained('users')->onDelete('cascade'); // Vekil
            $table->date('start_date');
            $table->date('end_date');
            $table->string('entity_type')->nullable(); // null = tüm tipler için
            $table->boolean('is_active')->default(true);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['company_id', 'delegator_id', 'start_date', 'end_date'], 'approval_delegations_idx');
        });
        \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
        Schema::dropIfExists('approval_records');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
