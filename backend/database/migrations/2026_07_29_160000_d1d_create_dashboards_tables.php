<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D1d — Rapor motoru Dashboard v2 (employee_dashboards dokunulmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('layout')->nullable(); // widgets[] + grid
            $table->jsonb('global_filters')->nullable(); // parametre tanımları
            $table->boolean('is_system')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'owner_id']);
            $table->index(['company_id', 'is_system']);
        });

        Schema::create('dashboard_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dashboard_id')->constrained('dashboards')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->string('level', 16)->default('viewer'); // viewer|editor
            $table->timestamps();

            $table->index(['dashboard_id', 'user_id']);
            $table->index(['dashboard_id', 'role_id']);
            $table->index(['company_id', 'dashboard_id']);
        });

        DB::statement("ALTER TABLE dashboard_shares ADD CONSTRAINT dashboard_shares_level_check CHECK (level IN ('viewer', 'editor'))");
        DB::statement('ALTER TABLE dashboard_shares ADD CONSTRAINT dashboard_shares_target_check CHECK (
            (user_id IS NOT NULL AND role_id IS NULL) OR (user_id IS NULL AND role_id IS NOT NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_shares');
        Schema::dropIfExists('dashboards');
    }
};
