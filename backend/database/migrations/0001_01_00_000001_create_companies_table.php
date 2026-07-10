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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Şirket adı
            $table->string('slug')->unique(); // URL-friendly isim
            $table->string('legal_name')->nullable(); // Resmi unvan
            $table->string('tax_office')->nullable(); // Vergi dairesi
            $table->string('tax_number')->nullable(); // Vergi numarası
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->default('Türkiye');
            $table->string('sector')->nullable(); // Sektör
            $table->string('employee_count')->nullable(); // Çalışan sayı aralığı
            $table->string('logo')->nullable(); // Logo dosya yolu
            $table->json('settings')->nullable(); // Firma ayarları (tema, dil vb.)
            
            // Lisans bilgileri
            $table->enum('package_type', ['starter', 'professional', 'enterprise'])->default('starter');
            $table->integer('user_limit')->default(5); // Kullanıcı limiti
            $table->bigInteger('storage_limit')->default(1073741824); // Storage limiti (byte) - 1GB default
            $table->date('license_start_date')->nullable();
            $table->date('license_end_date')->nullable();
            
            // Durum
            $table->enum('status', ['active', 'suspended', 'cancelled', 'trial'])->default('trial');
            $table->timestamp('trial_ends_at')->nullable();
            
            // Audit
            $table->timestamps();
            $table->softDeletes();
            
            // İndeksler
            $table->index('status');
            $table->index('package_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

