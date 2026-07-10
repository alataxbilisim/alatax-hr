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
        // Talep türleri
        Schema::create('request_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            
            // Ayarlar
            $table->boolean('requires_approval')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->json('approval_flow')->nullable(); // Onay akışı
            $table->json('form_fields')->nullable(); // Özel form alanları
            
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'slug']);
        });

        // Personel talepleri
        Schema::create('employee_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->foreignId('request_type_id')->constrained()->onDelete('cascade');
            
            // Talep içeriği
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('form_data')->nullable(); // Özel form verileri
            
            // Durum
            $table->string('status')->default('pending'); // pending, in_review, approved, rejected, cancelled
            $table->text('rejection_reason')->nullable();
            
            // Ekler
            $table->json('attachments')->nullable();
            
            // Onay bilgileri
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            
            // Öncelik
            $table->string('priority')->default('normal'); // low, normal, high, urgent
            
            // Tarihler
            $table->date('effective_date')->nullable(); // Geçerlilik tarihi
            $table->date('due_date')->nullable(); // Son tarih
            
            $table->text('notes')->nullable();
            $table->text('admin_notes')->nullable(); // İK notları (personel göremez)
            
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['employee_id', 'status']);
            $table->index(['company_id', 'request_type_id']);
        });

        // Talep geçmişi (durum değişiklikleri)
        Schema::create('employee_request_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_request_id')->constrained()->onDelete('cascade');
            $table->string('old_status')->nullable();
            $table->string('new_status');
            $table->text('comment')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_request_history');
        Schema::dropIfExists('employee_requests');
        Schema::dropIfExists('request_types');
    }
};

