<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * C6 — job_positions.form_definition_id köprüsü (legacy form_id kalır).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->foreignId('form_definition_id')
                ->nullable()
                ->after('form_id')
                ->constrained('form_definitions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_positions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('form_definition_id');
        });
    }
};
