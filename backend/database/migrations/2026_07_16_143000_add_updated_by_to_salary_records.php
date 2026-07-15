<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** B11: salary_records updated_by (HasAuditColumns). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_records', function (Blueprint $table) {
            if (! Schema::hasColumn('salary_records', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('salary_records', function (Blueprint $table) {
            if (Schema::hasColumn('salary_records', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }
        });
    }
};
