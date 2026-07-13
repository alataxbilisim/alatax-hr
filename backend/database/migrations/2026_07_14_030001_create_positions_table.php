<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAZ A5 — Firma unvan / pozisyon kataloğu (employees.position serbest metinle uyumlu).
 * Not: recruitment.job_positions iş ilanı tablosudur; bu katalog ayrıdır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 50);
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            /** İŞKUR / SGK meslek kodu (ISCO-08 tabanlı 6 hane örn. 2512.05) */
            $table->string('sgk_occupation_code', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'code']);
            $table->index(['company_id', 'is_active']);
            $table->index(['company_id', 'department_id']);
            $table->index(['company_id', 'sgk_occupation_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
