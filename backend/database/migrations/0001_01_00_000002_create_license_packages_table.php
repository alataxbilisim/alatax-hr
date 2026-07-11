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
        Schema::create('license_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Paket adı (Starter, Professional, Enterprise)
            $table->string('slug')->unique(); // URL-friendly isim
            $table->text('description')->nullable(); // Paket açıklaması

            // Fiyatlandırma
            $table->decimal('base_price', 10, 2)->default(0); // Aylık temel ücret
            $table->decimal('annual_price', 10, 2)->nullable(); // Yıllık ücret (indirimli)

            // Limitler
            $table->integer('user_limit')->default(5); // Kullanıcı limiti (0 = sınırsız)
            $table->integer('location_limit')->default(1); // Lokasyon limiti (0 = sınırsız)
            $table->integer('employee_limit')->default(50); // Personel limiti (0 = sınırsız)
            $table->integer('storage_limit_gb')->default(5); // Depolama limiti GB (0 = sınırsız)

            // Lisans süresi
            $table->integer('duration_months')->default(12); // Varsayılan lisans süresi (ay)

            // Durum
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false); // Öne çıkan paket
            $table->integer('sort_order')->default(0); // Sıralama

            // Ek ayarlar
            $table->jsonb('settings')->nullable(); // JSON - ek özellikler
            $table->jsonb('features')->nullable(); // JSON - özellik listesi (UI için)

            // Audit (foreign key'ler users tablosu oluşturulduktan sonra eklenecek)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // İndeksler
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_packages');
    }
};
