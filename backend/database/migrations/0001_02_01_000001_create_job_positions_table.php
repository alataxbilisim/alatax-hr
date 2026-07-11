<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->text('responsibilities')->nullable();
            $table->string('department')->nullable();
            $table->string('location')->nullable();
            \App\Support\PortableEnum::column($table, 'employment_type', ['full_time', 'part_time', 'contract', 'internship', 'remote'], 'full_time', false, 64, null);
            \App\Support\PortableEnum::column($table, 'experience_level', ['entry', 'mid', 'senior', 'lead', 'manager'], 'mid', false, 64, null);
            $table->decimal('salary_min', 10, 2)->nullable();
            $table->decimal('salary_max', 10, 2)->nullable();
            $table->boolean('salary_visible')->default(false);
            $table->foreignId('form_id')->nullable()->constrained('application_forms')->nullOnDelete();
            \App\Support\PortableEnum::column($table, 'status', ['draft', 'active', 'paused', 'closed'], 'draft', false, 64, null);
            $table->integer('positions_count')->default(1);
            $table->date('application_deadline')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
            \App\Support\PortableEnum::flushChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('job_positions');
    }
};
