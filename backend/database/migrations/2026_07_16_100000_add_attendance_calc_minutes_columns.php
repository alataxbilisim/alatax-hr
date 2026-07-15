<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDKS-2: geç / erken / eksik dakika alanları (ekleyici).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_records', 'late_minutes')) {
                $table->unsignedInteger('late_minutes')->default(0)->after('overtime_hours');
            }
            if (! Schema::hasColumn('attendance_records', 'early_leave_minutes')) {
                $table->unsignedInteger('early_leave_minutes')->default(0)->after('late_minutes');
            }
            if (! Schema::hasColumn('attendance_records', 'missing_minutes')) {
                $table->unsignedInteger('missing_minutes')->default(0)->after('early_leave_minutes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_records', 'missing_minutes')) {
                $table->dropColumn('missing_minutes');
            }
            if (Schema::hasColumn('attendance_records', 'early_leave_minutes')) {
                $table->dropColumn('early_leave_minutes');
            }
            if (Schema::hasColumn('attendance_records', 'late_minutes')) {
                $table->dropColumn('late_minutes');
            }
        });
    }
};
