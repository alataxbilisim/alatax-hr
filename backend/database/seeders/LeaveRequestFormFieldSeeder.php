<?php

namespace Database\Seeders;

use App\Services\FormFieldCatalogService;
use Illuminate\Database\Seeder;

/**
 * İzin talebi Form Engine sistem alanları — EmployeeFormFieldSeeder ile aynı
 * FormFieldCatalogService::seedSystemCatalog (idempotent; her iki entity).
 */
class LeaveRequestFormFieldSeeder extends Seeder
{
    public function run(): void
    {
        app(FormFieldCatalogService::class)->seedSystemCatalog();
    }
}
