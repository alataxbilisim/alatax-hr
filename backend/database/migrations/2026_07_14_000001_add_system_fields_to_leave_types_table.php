<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FAZ A1 — leave_types: system_code + is_system + deducts_from_annual (1B).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->string('system_code', 64)->nullable()->after('code');
            $table->boolean('is_system')->default(false)->after('system_code');
            $table->boolean('deducts_from_annual')->default(false)->after('is_paid');
            $table->index(['company_id', 'system_code']);
        });

        // Mevcut kısa kod → system_code eşlemesi (backfill)
        $map = [
            'YI' => 'annual',
            'EI' => 'marriage',
            'OI' => 'bereavement',
            'DI' => 'maternity',
            'BI' => 'paternity',
            'HI' => 'sick',
            'UI' => 'unpaid',
            'MI' => 'travel', // eski genel mazeret → yol/mazeret ailesi
        ];

        foreach ($map as $code => $systemCode) {
            DB::table('leave_types')
                ->where('code', $code)
                ->whereNull('system_code')
                ->update([
                    'system_code' => $systemCode,
                    'is_system' => true,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('leave_types', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'system_code']);
            $table->dropColumn(['system_code', 'is_system', 'deducts_from_annual']);
        });
    }
};
