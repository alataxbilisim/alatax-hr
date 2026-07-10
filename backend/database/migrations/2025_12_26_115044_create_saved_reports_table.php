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
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('config'); // Rapor konfigürasyonu (dimension, measure, chartType, filters, options)
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_shared')->default(false); // Diğer kullanıcılarla paylaşım
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // İndeksler
            $table->index(['company_id', 'user_id']);
            $table->index(['company_id', 'is_shared']);
            $table->index('is_favorite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_reports');
    }
};
