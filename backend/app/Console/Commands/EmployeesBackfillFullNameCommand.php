<?php

namespace App\Console\Commands;

use App\Models\Employee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Tur8 — employees.full_name boş olanları users.name'den doldur (idempotent).
 */
class EmployeesBackfillFullNameCommand extends Command
{
    protected $signature = 'employees:backfill-full-name {--dry-run : Sadece say}';

    protected $description = 'Boş employees.full_name alanlarını bağlı users.name ile doldur';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('production ortamında çalıştırılamaz.');

            return self::FAILURE;
        }

        $empty = Employee::withoutGlobalScopes()
            ->where(function ($q) {
                $q->whereNull('full_name')->orWhere('full_name', '');
            });

        $totalEmpty = (clone $empty)->count();
        $withUser = (clone $empty)->whereNotNull('user_id')->count();
        $noUser = $totalEmpty - $withUser;

        $this->info("Boş full_name: {$totalEmpty} (user var: {$withUser}, user yok: {$noUser})");

        if ($this->option('dry-run') || $withUser === 0) {
            if ($noUser > 0) {
                $this->warn("{$noUser} kayıtta user_id yok — ad hiç girilmemiş; otomatik doldurulamaz.");
            }

            return self::SUCCESS;
        }

        $updated = 0;
        Employee::withoutGlobalScopes()
            ->whereNotNull('user_id')
            ->where(function ($q) {
                $q->whereNull('full_name')->orWhere('full_name', '');
            })
            ->with('user')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use (&$updated): void {
                foreach ($rows as $employee) {
                    $name = trim((string) ($employee->user?->name ?? ''));
                    if ($name === '') {
                        continue;
                    }
                    DB::table('employees')->where('id', $employee->id)->update(['full_name' => $name]);
                    $updated++;
                }
            });

        $this->info("Güncellenen: {$updated}");
        if ($noUser > 0) {
            $this->warn("{$noUser} kayıtta user_id yok — elle veya yeniden kayıt gerekir.");
        }

        return self::SUCCESS;
    }
}
