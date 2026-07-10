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
        // Eğitimler
        Schema::create('trainings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title'); // Eğitim adı
            $table->text('description')->nullable();
            $table->string('category')->nullable(); // Teknik, Yönetsel, Zorunlu vb.
            $table->enum('type', ['online', 'classroom', 'hybrid'])->default('classroom');
            $table->string('instructor')->nullable(); // Eğitmen
            $table->string('location')->nullable(); // Mekan
            $table->integer('duration_hours')->nullable(); // Süre (saat)
            $table->integer('max_participants')->nullable(); // Maks katılımcı
            $table->decimal('cost', 10, 2)->default(0); // Maliyet
            $table->boolean('is_mandatory')->default(false); // Zorunlu mu?
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainings');
    }
};
