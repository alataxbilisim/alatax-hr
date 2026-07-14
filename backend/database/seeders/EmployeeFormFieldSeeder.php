<?php

namespace Database\Seeders;

use App\Services\FormFieldCatalogService;
use Illuminate\Database\Seeder;

/**
 * FAZ 4A-1 — Personel sistem alan kataloğu + varsayılan form layout (idempotent).
 */
class EmployeeFormFieldSeeder extends Seeder
{
    public function run(): void
    {
        $result = app(FormFieldCatalogService::class)->seedSystemCatalog();

        if ($this->command) {
            $this->command->info(
                "Employee form fields seeded: {$result['fields']} system fields, {$result['layouts']} layout(s)."
            );
        }
    }
}
