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
        // Performans dönemleri (değerlendirme periyotları)
        Schema::create('performance_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name'); // Q1 2024, Yıllık 2024, vb.
            $table->date('start_date');
            $table->date('end_date');
            \App\Support\PortableEnum::column($table, 'status', ['draft', 'active', 'closed'], 'draft', false, 64, null);
            $table->text('description')->nullable();
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
        Schema::dropIfExists('performance_periods');
    }
};
