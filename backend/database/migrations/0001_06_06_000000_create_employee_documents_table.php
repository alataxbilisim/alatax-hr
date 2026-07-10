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
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            
            // Belge bilgileri
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category'); // id_card, contract, certificate, education, health, other
            
            // Dosya
            $table->string('file_path');
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            
            // Geçerlilik
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->boolean('is_expired')->default(false);
            
            // Görünürlük
            $table->boolean('is_visible_to_employee')->default(true); // Personel görebilir mi?
            
            // Durum
            $table->string('status')->default('active'); // active, archived, expired
            
            $table->text('notes')->nullable();
            
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'employee_id']);
            $table->index(['employee_id', 'category']);
            $table->index(['employee_id', 'is_visible_to_employee']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};

