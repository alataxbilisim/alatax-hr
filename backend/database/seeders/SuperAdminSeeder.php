<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@alataxbilisim.com'],
            [
                'name' => 'Super Admin',
                'email' => 'admin@alataxbilisim.com',
                'password' => Hash::make('Admin123!'),
                'type' => 'super_admin',
                'company_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
                'preferences' => [
                    'theme' => 'dark',
                    'locale' => 'tr',
                ],
            ]
        );
    }
}

