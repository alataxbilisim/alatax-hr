<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma bazlı bildirim şablon override (4C-2).
 * Boş / yok = sistem varsayılanı (lang + katalog).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('event_key', 100);
            $table->string('subject', 255);
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'event_key']);
            $table->index(['company_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
