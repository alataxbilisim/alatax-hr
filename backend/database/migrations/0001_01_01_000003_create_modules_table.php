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
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Modül adı
            $table->string('slug')->unique(); // URL-friendly isim
            $table->text('description')->nullable();
            $table->string('icon')->nullable(); // İkon sınıfı
            $table->boolean('is_core')->default(false); // Temel modül mü?
            $table->decimal('price_monthly', 10, 2)->default(0); // Aylık fiyat
            $table->decimal('price_yearly', 10, 2)->default(0); // Yıllık fiyat
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Firma-Modül pivot tablosu
        Schema::create('company_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->date('activated_at')->nullable();
            $table->date('expires_at')->nullable();
            $table->json('settings')->nullable(); // Modül bazlı firma ayarları
            $table->timestamps();
            
            $table->unique(['company_id', 'module_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_modules');
        Schema::dropIfExists('modules');
    }
};

