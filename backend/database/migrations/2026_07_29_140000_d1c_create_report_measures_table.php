<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D1c — dataset seviyesi ölçü kütüphanesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_measures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('dataset_key', 64);
            $table->string('key', 64);
            $table->string('label');
            $table->text('expression');
            $table->string('format', 32)->default('number');
            $table->unsignedTinyInteger('decimals')->default(2);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'dataset_key', 'key']);
            $table->index(['company_id', 'dataset_key']);
        });

        DB::statement("ALTER TABLE report_measures ADD CONSTRAINT report_measures_format_check CHECK (format IN ('number', 'money', 'percent'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('report_measures');
    }
};
