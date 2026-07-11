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
        // Varlıklar (demirbaşlar)
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('asset_categories')->cascadeOnDelete();
            $table->string('name'); // Varlık adı
            $table->string('asset_code')->nullable(); // Demirbaş kodu
            $table->string('serial_number')->nullable(); // Seri numarası
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->text('description')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 12, 2)->nullable();
            $table->date('warranty_end_date')->nullable();
            \App\Support\PortableEnum::column($table, 'condition', ['new', 'good', 'fair', 'poor', 'broken'], 'new', false, 64, null);
            \App\Support\PortableEnum::column($table, 'status', ['available', 'assigned', 'maintenance', 'disposed'], 'available', false, 64, null);
            $table->string('location')->nullable();
            $table->jsonb('specifications')->nullable(); // Teknik özellikler
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'asset_code']);
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
