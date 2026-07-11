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
        // Resmi tatiller ve şirket tatilleri
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->onDelete('cascade'); // null = sistem geneli
            $table->string('name');
            $table->date('date');
            $table->date('end_date')->nullable(); // Çok günlük tatiller için
            \App\Support\PortableEnum::column($table, 'type', ['national', 'religious', 'company', 'regional'], 'national', false, 64, null);
            $table->string('country_code', 2)->default('TR');
            $table->boolean('is_recurring')->default(false); // Her yıl tekrar eder mi?
            $table->boolean('is_half_day')->default(false); // Yarım gün mü?
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'date']);
            $table->index(['country_code', 'date']);
        });

        // Leave request tablosuna workflow alanları ekle
        Schema::table('leave_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_requests', 'approval_workflow_id')) {
                $table->foreignId('approval_workflow_id')->nullable()->after('status')->constrained()->onDelete('set null');
            }
            if (! Schema::hasColumn('leave_requests', 'current_step')) {
                $table->integer('current_step')->nullable()->after('approval_workflow_id');
            }
            if (! Schema::hasColumn('leave_requests', 'workflow_status')) {
                \App\Support\PortableEnum::column($table, 'workflow_status', ['pending', 'in_progress', 'completed', 'rejected'], 'pending', false, 64, 'current_step');
            }
        });

        // Leave types tablosuna workflow bağlantısı ekle
        Schema::table('leave_types', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_types', 'approval_workflow_id')) {
                $table->foreignId('approval_workflow_id')->nullable()->after('approval_flow')->constrained()->onDelete('set null');
            }
            if (! Schema::hasColumn('leave_types', 'accrual_policy_id')) {
                $table->foreignId('accrual_policy_id')->nullable()->after('approval_workflow_id')->constrained()->onDelete('set null');
            }
        });

        // Leave balances tablosuna devir alanları ekle
        Schema::table('leave_balances', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_balances', 'carried_over')) {
                $table->decimal('carried_over', 8, 2)->default(0)->after('used_days');
            }
            if (! Schema::hasColumn('leave_balances', 'accrued')) {
                $table->decimal('accrued', 8, 2)->default(0)->after('carried_over');
            }
            if (! Schema::hasColumn('leave_balances', 'encashed')) {
                $table->decimal('encashed', 8, 2)->default(0)->after('accrued');
            }
            if (! Schema::hasColumn('leave_balances', 'expired')) {
                $table->decimal('expired', 8, 2)->default(0)->after('encashed');
            }
            if (! Schema::hasColumn('leave_balances', 'carryover_expiry')) {
                $table->date('carryover_expiry')->nullable()->after('expired');
            }
        });
        \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leave_balances', function (Blueprint $table) {
            $table->dropColumn(['carried_over', 'accrued', 'encashed', 'expired', 'carryover_expiry']);
        });

        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropForeign(['approval_workflow_id']);
            $table->dropForeign(['accrual_policy_id']);
            $table->dropColumn(['approval_workflow_id', 'accrual_policy_id']);
        });

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropForeign(['approval_workflow_id']);
            $table->dropColumn(['approval_workflow_id', 'current_step', 'workflow_status']);
        });

        Schema::dropIfExists('holidays');
    }
};
