<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduler: süreli evrak uyarı idempotency (eşik başına bir bildirim).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_expiry_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_document_id')->constrained('employee_documents')->cascadeOnDelete();
            $table->unsignedSmallInteger('threshold_days');
            $table->timestamp('notified_at');

            $table->unique(['employee_document_id', 'threshold_days'], 'document_expiry_alerts_doc_threshold_uq');
            $table->index(['company_id', 'threshold_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_expiry_alerts');
    }
};
