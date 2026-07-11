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
        Schema::create('onboarding_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('process_id')->constrained('onboarding_processes')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            \App\Support\PortableEnum::column($table, 'type', ['document_upload', 'document_fill', 'training', 'meeting', 'system_setup', 'quiz', 'custom'], 'custom', false, 64, null);
            $table->integer('order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->date('due_date')->nullable();
            \App\Support\PortableEnum::column($table, 'status', ['pending', 'in_progress', 'completed', 'skipped'], 'pending', false, 64, null);
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->jsonb('data')->nullable(); // Ek veriler (form cevapları, yüklenen dosyalar vb.)
            $table->text('notes')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['process_id', 'order']);
        });
        \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_tasks');
    }
};
