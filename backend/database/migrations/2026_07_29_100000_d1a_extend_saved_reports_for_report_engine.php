<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D1a — Rapor motoru: dataset_key + paylaşım + sistem bayrağı.
 * Mevcut personel rapor config jsonb korunur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->string('dataset_key', 64)->nullable()->after('description');
            $table->jsonb('share_user_ids')->nullable()->after('is_shared');
            $table->jsonb('share_role_ids')->nullable()->after('share_user_ids');
            $table->boolean('is_system')->default(false)->after('share_role_ids');
            $table->index(['company_id', 'dataset_key']);
        });
    }

    public function down(): void
    {
        Schema::table('saved_reports', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'dataset_key']);
            $table->dropColumn(['dataset_key', 'share_user_ids', 'share_role_ids', 'is_system']);
        });
    }
};
