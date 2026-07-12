<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * work_type lookup birleşik/açık set — employment_type CHECK kaldırılır;
 * doğrulama LookupService ile yapılır (firma yeni value ekleyebilir).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE job_positions DROP CONSTRAINT IF EXISTS job_positions_employment_type_check');
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            try {
                DB::statement('ALTER TABLE job_positions DROP CHECK job_positions_employment_type_check');
            } catch (\Throwable) {
                // yok
            }
        }
    }

    public function down(): void
    {
        \App\Support\PortableEnum::addCheck('job_positions', 'employment_type', [
            'full_time',
            'part_time',
            'contract',
            'internship',
            'remote',
        ]);
    }
};
