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
        // Eğitim oturumları (planlanmış eğitimler)
        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_id')->constrained()->cascadeOnDelete();
            $table->datetime('start_date');
            $table->datetime('end_date');
            $table->string('location')->nullable();
            $table->string('instructor')->nullable();
            \App\Support\PortableEnum::column($table, 'status', ['scheduled', 'in_progress', 'completed', 'cancelled'], 'scheduled', false, 64, null);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
            \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('training_sessions');
    }
};
