<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D1e — Paylaşım v2 + klasörler + erişim denetimi (motor dokunulmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('report_folders')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'parent_id']);
            $table->index(['company_id', 'sort_order']);
        });

        Schema::create('report_shares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('saved_report_id')->constrained('saved_reports')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('roles')->cascadeOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->cascadeOnDelete();
            $table->string('level', 16)->default('viewer');
            $table->timestamps();

            $table->index(['saved_report_id', 'user_id']);
            $table->index(['saved_report_id', 'role_id']);
            $table->index(['saved_report_id', 'department_id']);
            $table->index(['company_id', 'saved_report_id']);
        });

        DB::statement("ALTER TABLE report_shares ADD CONSTRAINT report_shares_level_check CHECK (level IN ('viewer', 'editor'))");
        DB::statement('ALTER TABLE report_shares ADD CONSTRAINT report_shares_target_check CHECK (
            (user_id IS NOT NULL AND role_id IS NULL AND department_id IS NULL)
            OR (user_id IS NULL AND role_id IS NOT NULL AND department_id IS NULL)
            OR (user_id IS NULL AND role_id IS NULL AND department_id IS NOT NULL)
        )');

        Schema::table('dashboard_shares', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('role_id')->constrained('departments')->cascadeOnDelete();
            $table->index(['dashboard_id', 'department_id']);
        });

        DB::statement('ALTER TABLE dashboard_shares DROP CONSTRAINT IF EXISTS dashboard_shares_target_check');
        DB::statement('ALTER TABLE dashboard_shares ADD CONSTRAINT dashboard_shares_target_check CHECK (
            (user_id IS NOT NULL AND role_id IS NULL AND department_id IS NULL)
            OR (user_id IS NULL AND role_id IS NOT NULL AND department_id IS NULL)
            OR (user_id IS NULL AND role_id IS NULL AND department_id IS NOT NULL)
        )');

        Schema::table('saved_reports', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('company_id')->constrained('report_folders')->nullOnDelete();
            $table->index(['company_id', 'folder_id']);
        });

        Schema::table('dashboards', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('company_id')->constrained('report_folders')->nullOnDelete();
            $table->index(['company_id', 'folder_id']);
        });

        Schema::create('report_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('saved_reports')->nullOnDelete();
            $table->foreignId('dashboard_id')->nullable()->constrained('dashboards')->nullOnDelete();
            $table->string('action', 32); // run|preview|export|drill_details
            $table->string('dataset_key', 64)->nullable();
            $table->unsignedInteger('row_count')->default(0);
            $table->boolean('contains_sensitive')->default(false);
            $table->jsonb('sensitive_fields')->nullable();
            $table->string('filters_hash', 64)->nullable();
            $table->jsonb('filter_field_keys')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'user_id', 'created_at']);
            $table->index(['company_id', 'contains_sensitive']);
            $table->index(['company_id', 'report_id']);
            $table->index(['company_id', 'dashboard_id']);
        });

        DB::statement("ALTER TABLE report_access_logs ADD CONSTRAINT report_access_logs_action_check CHECK (action IN ('run', 'preview', 'export', 'drill_details'))");
        DB::statement('ALTER TABLE report_access_logs ADD CONSTRAINT report_access_logs_target_check CHECK (
            (report_id IS NOT NULL AND dashboard_id IS NULL)
            OR (report_id IS NULL AND dashboard_id IS NOT NULL)
            OR (report_id IS NULL AND dashboard_id IS NULL)
        )');
    }

    public function down(): void
    {
        Schema::dropIfExists('report_access_logs');

        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('folder_id');
        });

        DB::statement('ALTER TABLE dashboard_shares DROP CONSTRAINT IF EXISTS dashboard_shares_target_check');
        Schema::table('dashboard_shares', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
        });
        DB::statement('ALTER TABLE dashboard_shares ADD CONSTRAINT dashboard_shares_target_check CHECK (
            (user_id IS NOT NULL AND role_id IS NULL) OR (user_id IS NULL AND role_id IS NOT NULL)
        )');

        Schema::dropIfExists('report_shares');
        Schema::dropIfExists('report_folders');
    }
};
