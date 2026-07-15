<?php

namespace Database\Seeders;

use App\Services\FormFieldCatalogService;
use Illuminate\Database\Seeder;

/**
 * İş başvurusu Form Engine sistem alanları — seedSystemCatalog (idempotent).
 */
class JobApplicationFormFieldSeeder extends Seeder
{
    public function run(): void
    {
        app(FormFieldCatalogService::class)->seedSystemCatalog();
    }
}
