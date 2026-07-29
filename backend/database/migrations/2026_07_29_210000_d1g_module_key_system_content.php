<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D1g — module_key + sistem içerik (company_id NULL şablon) + rol varsayılan pano.
 */
return new class extends Migration
{
    public function up(): void
    {
        // saved_reports: sistem şablonları company_id/user_id NULL
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['user_id']);
        });
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->string('module_key', 64)->nullable()->after('dataset_key');
            $table->string('system_key', 128)->nullable()->after('module_key');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['module_key', 'is_system']);
        });
        DB::statement('CREATE UNIQUE INDEX saved_reports_system_key_unique ON saved_reports (system_key) WHERE company_id IS NULL AND system_key IS NOT NULL AND is_system = true');

        // dashboards
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['owner_id']);
        });
        Schema::table('dashboards', function (Blueprint $table) {
            $table->unsignedBigInteger('company_id')->nullable()->change();
            $table->unsignedBigInteger('owner_id')->nullable()->change();
            $table->string('module_key', 64)->nullable()->after('name');
            $table->string('system_key', 128)->nullable()->after('module_key');
            $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['module_key', 'is_system']);
        });
        DB::statement('CREATE UNIQUE INDEX dashboards_system_key_unique ON dashboards (system_key) WHERE company_id IS NULL AND system_key IS NOT NULL AND is_system = true');

        Schema::create('role_default_dashboards', function (Blueprint $table) {
            $table->id();
            $table->string('role_key', 64); // company_admin | hr | manager
            $table->string('dashboard_system_key', 128);
            $table->timestamps();
            $table->unique('role_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_default_dashboards');

        DB::statement('DROP INDEX IF EXISTS dashboards_system_key_unique');
        Schema::table('dashboards', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['owner_id']);
            $table->dropIndex(['module_key', 'is_system']);
            $table->dropColumn(['module_key', 'system_key']);
        });
        // company_id/owner_id nullable geri alınmaz (veri kaybı riski) — DUR

        DB::statement('DROP INDEX IF EXISTS saved_reports_system_key_unique');
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropForeign(['user_id']);
            $table->dropIndex(['module_key', 'is_system']);
            $table->dropColumn(['module_key', 'system_key']);
        });
    }
};
