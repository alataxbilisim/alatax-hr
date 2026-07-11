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
        // Eğitim katılımcıları
        Schema::create('training_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('training_sessions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            \App\Support\PortableEnum::column($table, 'status', ['registered', 'attended', 'absent', 'excused'], 'registered', false, 64, null);
            $table->integer('score')->nullable(); // Sınav puanı
            $table->boolean('passed')->nullable(); // Başarılı mı?
            $table->text('feedback')->nullable(); // Geri bildirim
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'user_id']);
        });
        \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_participants');
    }
};
