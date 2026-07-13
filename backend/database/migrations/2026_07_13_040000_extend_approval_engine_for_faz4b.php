<?php

use App\Support\PortableEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 4B B0: onay adımlarını genişlet + polymorphic approval_instances.
 * Baseline approval migration'ına dokunulmaz.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const APPROVER_TYPES = [
        'direct_manager',
        'department_head',
        'specific_user',
        'specific_role',
        'hr',
        'cfo',
        'ceo',
        // Faz 4B adlandırma (alias'lar findApprover'da eşlenir)
        'dynamic_manager',
        'dynamic_skip_manager',
        'role',
        'user',
    ];

    /** @var list<string> */
    private const INSTANCE_STATUSES = [
        'pending',
        'in_progress',
        'approved',
        'rejected',
        'cancelled',
    ];

    /** @var list<string> */
    private const COMPLETION_POLICIES = [
        'all',
        'any',
    ];

    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->jsonb('condition')->nullable()->after('timeout_action');
            $table->unsignedInteger('parallel_group')->nullable()->after('condition');
            PortableEnum::column(
                $table,
                'completion_policy',
                self::COMPLETION_POLICIES,
                'all',
                true,
                16,
                'parallel_group'
            );
        });

        PortableEnum::addCheck('approval_steps', 'approver_type', self::APPROVER_TYPES);
        PortableEnum::flushChecks();

        Schema::create('approval_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->morphs('approvable');
            $table->unsignedInteger('current_step')->default(1);
            PortableEnum::column($table, 'status', self::INSTANCE_STATUSES, 'pending', false, 32, null);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'approval_workflow_id']);
        });
        PortableEnum::flushChecks();

        Schema::table('approval_records', function (Blueprint $table) {
            $table->foreignId('approval_instance_id')
                ->nullable()
                ->after('company_id')
                ->constrained('approval_instances')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approval_instance_id');
        });

        Schema::dropIfExists('approval_instances');

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn(['condition', 'parallel_group', 'completion_policy']);
        });

        // Eski CHECK setine geri dön
        PortableEnum::addCheck('approval_steps', 'approver_type', [
            'direct_manager',
            'department_head',
            'specific_user',
            'specific_role',
            'hr',
            'cfo',
            'ceo',
        ]);
    }
};
