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
        Schema::create('license_package_modules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_package_id')->constrained('license_packages')->onDelete('cascade');
            // module_id foreign key modules tablosu oluşturulduktan sonra eklenecek
            $table->unsignedBigInteger('module_id');
            $table->boolean('is_included')->default(true); // Pakete dahil mi
            $table->decimal('additional_price', 10, 2)->default(0); // Ek ücret (hibrit model için)
            $table->timestamps();

            // Unique constraint
            $table->unique(['license_package_id', 'module_id'], 'pkg_module_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('license_package_modules');
    }
};
