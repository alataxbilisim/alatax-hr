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
        // Doküman Versiyonları
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->integer('version_number');
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->bigInteger('file_size')->default(0);
            $table->string('hash')->nullable(); // Dosya hash'i
            $table->text('change_notes')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
        });

        // Zorunlu Evrak Tanımları
        Schema::create('required_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_category_id')->nullable()->constrained()->onDelete('set null');

            $table->string('name'); // Kimlik Fotokopisi, Sağlık Raporu
            $table->text('description')->nullable();

            // Uygulanacak kapsam
            \App\Support\PortableEnum::column($table, 'scope', ['all', 'department', 'position', 'employee_type'], 'all', false, 64, null);
            $table->string('scope_value')->nullable(); // Departman adı, pozisyon adı vb.

            $table->boolean('is_mandatory')->default(true);
            $table->integer('validity_months')->nullable(); // Geçerlilik süresi (ay)
            $table->integer('reminder_days_before')->default(30); // Kaç gün önce hatırlat

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'scope']);
        });

        // Kullanıcı Evrak Durumu (Eksik/Tamamlanmış)
        Schema::create('user_document_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('required_document_id')->constrained()->onDelete('cascade');
            $table->foreignId('document_id')->nullable()->constrained()->onDelete('set null');

            \App\Support\PortableEnum::column($table, 'status', ['missing', 'pending', 'approved', 'rejected', 'expired'], 'missing', false, 64, null);
            $table->date('expiry_date')->nullable();
            $table->date('last_reminded_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->datetime('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'required_document_id']);
        });

        // Evrak Onay Akışı
        Schema::create('document_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->onDelete('cascade');
            $table->foreignId('approver_id')->constrained('users')->onDelete('cascade');
            \App\Support\PortableEnum::column($table, 'status', ['pending', 'approved', 'rejected'], 'pending', false, 64, null);
            $table->text('comment')->nullable();
            $table->datetime('decided_at')->nullable();
            $table->timestamps();
        });

        // Documents tablosuna yeni alanlar ekle
        Schema::table('documents', function (Blueprint $table) {
            $table->integer('current_version')->default(1)->after('file_size');
            $table->date('validity_date')->nullable()->after('current_version');
            \App\Support\PortableEnum::column($table, 'approval_status', ['draft', 'pending', 'approved', 'rejected'], 'approved', false, 64, 'validity_date');
            $table->boolean('requires_approval')->default(false)->after('approval_status');
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn(['current_version', 'validity_date', 'approval_status', 'requires_approval']);
        });

        Schema::dropIfExists('document_approvals');
        Schema::dropIfExists('user_document_status');
        Schema::dropIfExists('required_documents');
        Schema::dropIfExists('document_versions');
    }
};
