<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ModuleSeeder::class,
            LicensePackageSeeder::class,
            PermissionSeeder::class,
            SystemReportPackageSeeder::class,
            SuperAdminSeeder::class,
            LeaveTypeSeeder::class,
            LookupSeeder::class,
            EmployeeFormFieldSeeder::class,
        ]);
    }
}
