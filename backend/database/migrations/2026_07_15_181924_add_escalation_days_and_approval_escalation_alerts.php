<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B4: eskalasyon SLA (nullable) + idempotent bildirim kaydı.
 * Yetki devretmez — yalnız hatırlatma / üst bilgilendirme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_steps', function (Blueprint $table) {
            $table->unsignedSmallInteger('escalation_days')->nullable()->after('completion_policy');
        });

        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->unsignedSmallInteger('escalation_days')->nullable()->after('conditions');
        });

        Schema::create('approval_escalation_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_record_id')->constrained('approval_records')->cascadeOnDelete();
            // reminder | escalated
            $table->string('alert_level', 32);
            $table->timestamp('notified_at');

            $table->unique(['approval_record_id', 'alert_level'], 'approval_escalation_alerts_record_level_uq');
            $table->index(['company_id', 'alert_level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_escalation_alerts');

        Schema::table('approval_workflows', function (Blueprint $table) {
            $table->dropColumn('escalation_days');
        });

        Schema::table('approval_steps', function (Blueprint $table) {
            $table->dropColumn('escalation_days');
        });
    }
};
