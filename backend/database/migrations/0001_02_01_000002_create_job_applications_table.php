<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('job_position_id')->constrained()->onDelete('cascade');

            // Aday bilgileri
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();

            // CV
            $table->string('cv_path')->nullable();
            $table->string('cv_original_name')->nullable();

            // Form verileri
            $table->json('form_data')->nullable();

            // Durum
            $table->enum('status', [
                'new',
                'reviewing',
                'shortlisted',
                'interview_scheduled',
                'interviewed',
                'offer_sent',
                'hired',
                'rejected',
                'withdrawn',
            ])->default('new');

            // Değerlendirme
            $table->integer('rating')->nullable(); // 1-5
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            // Kaynak
            $table->string('source')->nullable(); // website, linkedin, referral, etc.
            $table->string('referrer')->nullable();

            // Atama
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            // IP & User Agent
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index(['job_position_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_applications');
    }
};
