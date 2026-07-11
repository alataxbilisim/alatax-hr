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
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // İçerik
            $table->string('title');
            $table->text('content');
            $table->text('summary')->nullable(); // Kısa özet

            // Tip
            $table->string('type')->default('general'); // general, urgent, important, info
            $table->string('category')->nullable(); // hr, it, management, social

            // Hedef kitle
            $table->boolean('is_for_all')->default(true); // Tüm personele mi?
            $table->jsonb('target_departments')->nullable(); // Belirli departmanlar
            $table->jsonb('target_positions')->nullable(); // Belirli pozisyonlar
            $table->jsonb('target_employees')->nullable(); // Belirli personeller

            // Görsel
            $table->string('image_path')->nullable();
            $table->jsonb('attachments')->nullable(); // Ekler

            // Yayın durumu
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // Son geçerlilik tarihi

            // Pin (öne çıkarma)
            $table->boolean('is_pinned')->default(false);
            $table->integer('pin_order')->default(0);

            // Görüntülenme
            $table->integer('view_count')->default(0);

            // Onay gerektiriyor mu?
            $table->boolean('requires_acknowledgment')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'is_published']);
            $table->index(['company_id', 'type']);
            $table->index(['company_id', 'published_at']);
        });

        // Duyuru görüntüleme/onay takibi
        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->onDelete('cascade');
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->timestamp('read_at');
            $table->boolean('acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();

            $table->unique(['announcement_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
