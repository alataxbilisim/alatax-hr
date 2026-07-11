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
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name')->nullable(); // Kullanıcı silinse bile ismi kalsın

            // İşlem detayları
            $table->string('action'); // create, update, delete, login, logout, etc.
            $table->string('model_type')->nullable(); // Hangi model üzerinde işlem yapıldı
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('description')->nullable();

            // Değişiklik kaydı
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();

            // Request bilgileri
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();

            // Sonuç
            $table->boolean('is_successful')->default(true);
            $table->text('error_message')->nullable();

            $table->timestamp('created_at')->nullable();

            // İndeksler
            $table->index('company_id');
            $table->index('user_id');
            $table->index('action');
            $table->index('model_type');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
