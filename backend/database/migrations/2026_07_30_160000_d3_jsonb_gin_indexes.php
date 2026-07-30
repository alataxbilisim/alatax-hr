<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * D3 — JSONB GIN (yalnız @> ile filtrelenen kolonlar).
 *
 * Kanıt:
 * - SavedReport::scopeVisibleTo → share_user_ids @> / share_role_ids @>
 *
 * jsonb_path_ops: @> için daha küçük/hızlı; ->> ifade indeksi bu dalgada yok
 * (rapor motoru dinamik key — sabit yol yok).
 *
 * Eklenmeyen (filtre kanıtı yok / ->> only): users.preferences, dashboards.layout,
 * setting_values.value, employees.custom_fields liste API, announcements.target_*.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX IF NOT EXISTS saved_reports_share_user_ids_gin ON saved_reports USING gin (share_user_ids jsonb_path_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS saved_reports_share_role_ids_gin ON saved_reports USING gin (share_role_ids jsonb_path_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS saved_reports_share_user_ids_gin');
        DB::statement('DROP INDEX IF EXISTS saved_reports_share_role_ids_gin');
    }
};
