<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D2b — Veri sahibi talepleri + ihraç paketi + indirme logu.
 * Bu dalga veri SİLMEZ; silme talebi "destruction_pending" işaretlenir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_subject_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('subject_type', 32); // employee|candidate|former_employee|visitor|other
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('applicant_name');
            $table->string('contact'); // e-posta veya telefon
            $table->jsonb('request_types'); // list of type strings
            $table->text('description')->nullable();
            $table->string('channel', 32); // portal|public_form|email|written|kep
            $table->boolean('identity_verified')->default(false);
            $table->string('verification_method', 64)->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('status', 32)->default('new');
            $table->date('due_date');
            $table->timestamp('responded_at')->nullable();
            $table->text('response_body')->nullable();
            $table->string('response_file_path')->nullable();
            $table->string('response_template', 32)->nullable(); // accept|partial|reject
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->boolean('destruction_pending')->default(false); // D2c işleyecek
            $table->jsonb('destruction_scope')->nullable(); // silinemeyenler + bekleyenler
            $table->string('email_verify_token', 64)->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'due_date']);
            $table->index(['company_id', 'subject_type', 'subject_id']);
        });

        Schema::create('data_subject_export_packages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('data_subject_request_id')->constrained('data_subject_requests')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('status', 32)->default('pending'); // pending|ready|failed|expired|purged
            $table->string('storage_path')->nullable(); // private zip
            $table->string('json_path')->nullable();
            $table->string('human_path')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['expires_at']);
        });

        Schema::create('data_subject_export_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('export_package_id')->constrained('data_subject_export_packages')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32); // download|view_meta
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement("ALTER TABLE data_subject_requests ADD CONSTRAINT data_subject_requests_subject_type_check CHECK (subject_type IN ('employee','candidate','former_employee','visitor','other'))");
        DB::statement("ALTER TABLE data_subject_requests ADD CONSTRAINT data_subject_requests_channel_check CHECK (channel IN ('portal','public_form','email','written','kep'))");
        DB::statement("ALTER TABLE data_subject_requests ADD CONSTRAINT data_subject_requests_status_check CHECK (status IN ('new','identity_pending','in_review','awaiting_info','approved','partially_approved','rejected','completed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('data_subject_export_access_logs');
        Schema::dropIfExists('data_subject_export_packages');
        Schema::dropIfExists('data_subject_requests');
    }
};
