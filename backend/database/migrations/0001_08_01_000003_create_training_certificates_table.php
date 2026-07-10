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
        // Eğitim sertifikaları
        Schema::create('training_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('participant_id')->constrained('training_participants')->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->date('issue_date');
            $table->date('expiry_date')->nullable();
            $table->string('file_path')->nullable();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_certificates');
    }
};

