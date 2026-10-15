<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

/**
 * Demo şirketinde (DemoDataSeeder) adı boş DEM-xxx personeline seeder'daki adı yazar (idempotent).
 * Yalnız boş full_name doldurulur; dolu ad asla değişmez.
 */
class DemoFillEmployeeNamesCommand extends Command
{
    protected $signature = 'demo:fill-employee-names {--dry-run : Yalnız listele, yazma}';

    protected $description = 'Demo şirketinde adı boş DEM-xxx personeline seeder adını yaz';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('production ortamında çalıştırılamaz.');

            return self::FAILURE;
        }

        $company = Company::query()->where('slug', DemoDataSeeder::COMPANY_SLUG)->first();
        if ($company === null) {
            $this->info('Demo şirketi yok; yapılacak iş yok.');

            return self::SUCCESS;
        }

        $rows = [];
        for ($i = 1; $i <= count(DemoDataSeeder::EMPLOYEE_NAMES); $i++) {
            $code = sprintf('DEM-%03d', $i);
            $employee = Employee::withoutGlobalScopes()
                ->withTrashed()
                ->where('company_id', $company->id)
                ->where('employee_code', $code)
                ->where(fn ($q) => $q->whereNull('full_name')->orWhere('full_name', ''))
                ->first();

            if ($employee === null) {
                continue;
            }

            $name = DemoDataSeeder::employeeFullName($i);
            $rows[] = [$employee->id, $code, $employee->full_name ?? '(boş)', $name];

            if (! $this->option('dry-run')) {
                $employee->forceFill(['full_name' => $name])->save();
            }
        }

        $this->line($this->option('dry-run') ? 'DRY-RUN — yazılmadı.' : 'Güncellendi.');
        $this->table(['id', 'Kod', 'Önce', 'Sonra'], $rows);
        $this->info('Kayıt: '.count($rows));

        return self::SUCCESS;
    }
}
