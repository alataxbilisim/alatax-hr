<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form Engine custom alan depolama — leave_request / expense / asset.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leave_requests', 'custom_fields')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->jsonb('custom_fields')->nullable()->after('reason');
            });
        }

        if (! Schema::hasColumn('expense_claims', 'custom_fields')) {
            Schema::table('expense_claims', function (Blueprint $table) {
                $table->jsonb('custom_fields')->nullable()->after('description');
            });
        }

        if (! Schema::hasColumn('assets', 'custom_fields')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->jsonb('custom_fields')->nullable()->after('location');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leave_requests', 'custom_fields')) {
            Schema::table('leave_requests', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }

        if (Schema::hasColumn('expense_claims', 'custom_fields')) {
            Schema::table('expense_claims', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }

        if (Schema::hasColumn('assets', 'custom_fields')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }
    }
};
