<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FAZ B-2 — KVKK aday rızası + hired→personel dönüşüm bağlantısı (ekleyici).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->boolean('consent_kvkk')->default(false)->after('user_agent');
            $table->timestamp('consent_at')->nullable()->after('consent_kvkk');
            $table->foreignId('converted_employee_id')
                ->nullable()
                ->after('consent_at')
                ->constrained('employees')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('converted_employee_id');
            $table->dropColumn(['consent_kvkk', 'consent_at']);
        });
    }
};
