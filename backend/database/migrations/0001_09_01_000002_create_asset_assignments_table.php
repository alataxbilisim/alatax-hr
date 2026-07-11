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
        // Zimmet kayıtları
        Schema::create('asset_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('assigned_date');
            $table->date('return_date')->nullable();
            $table->text('notes')->nullable();
            \App\Support\PortableEnum::column($table, 'condition_at_assignment', ['new', 'good', 'fair', 'poor'], null, true, 64, null);
            \App\Support\PortableEnum::column($table, 'condition_at_return', ['good', 'fair', 'poor', 'broken'], null, true, 64, null);
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('returned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['asset_id', 'user_id']);
        });
        \App\Support\PortableEnum::flushChecks();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asset_assignments');
    }
};
