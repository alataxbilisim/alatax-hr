<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * C5: duyuru hedef şube + announcement_reads user_id hizalaması (portal kodu user_id kullanır).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('announcements') && ! Schema::hasColumn('announcements', 'target_branches')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->jsonb('target_branches')->nullable()->after('target_employees');
            });
        }

        if (! Schema::hasTable('announcement_reads')) {
            return;
        }

        if (! Schema::hasColumn('announcement_reads', 'user_id')) {
            Schema::table('announcement_reads', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('announcement_id')->constrained()->nullOnDelete();
            });
        }

        // employee_id → user_id backfill
        if (Schema::hasColumn('announcement_reads', 'employee_id')) {
            DB::statement(<<<'SQL'
                UPDATE announcement_reads AS ar
                SET user_id = e.user_id
                FROM employees AS e
                WHERE ar.employee_id = e.id
                  AND ar.user_id IS NULL
                  AND e.user_id IS NOT NULL
                SQL);
        }

        // Unique (announcement_id, user_id) — çakışan satırları temizle
        DB::statement(<<<'SQL'
            DELETE FROM announcement_reads a
            USING announcement_reads b
            WHERE a.id > b.id
              AND a.announcement_id = b.announcement_id
              AND a.user_id IS NOT NULL
              AND a.user_id = b.user_id
            SQL);

        $indexes = collect(DB::select(
            "SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'announcement_reads'"
        ));

        $hasUserUnique = $indexes->contains(
            fn ($row) => str_contains((string) $row->indexdef, '(announcement_id, user_id)')
                || str_contains((string) $row->indexdef, '(announcement_id,user_id)')
        );

        if (! $hasUserUnique) {
            Schema::table('announcement_reads', function (Blueprint $table) {
                $table->unique(['announcement_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('announcement_reads') && Schema::hasColumn('announcement_reads', 'user_id')) {
            Schema::table('announcement_reads', function (Blueprint $table) {
                $table->dropUnique(['announcement_id', 'user_id']);
                $table->dropConstrainedForeignId('user_id');
            });
        }

        if (Schema::hasTable('announcements') && Schema::hasColumn('announcements', 'target_branches')) {
            Schema::table('announcements', function (Blueprint $table) {
                $table->dropColumn('target_branches');
            });
        }
    }
};
