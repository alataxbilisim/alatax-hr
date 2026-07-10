<?php

namespace Database\Seeders;

use App\Models\LicensePackage;
use App\Models\Module;
use Illuminate\Database\Seeder;

class LicensePackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Küçük işletmeler için temel İK yönetimi.',
                'base_price' => 499.00,
                'annual_price' => 4990.00,
                'user_limit' => 5,
                'location_limit' => 1,
                'employee_limit' => 50,
                'storage_limit_gb' => 5,
                'duration_months' => 12,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 1,
                'features' => [
                    'Temel İK Yönetimi',
                    '5 Kullanıcı',
                    '1 Lokasyon',
                    '50 Personel',
                    '5 GB Depolama',
                    'E-posta Desteği',
                ],
                'modules' => ['core', 'job-applications'], // Core + 1 modül
            ],
            [
                'name' => 'Professional',
                'slug' => 'professional',
                'description' => 'Orta ölçekli firmalar için gelişmiş İK çözümü.',
                'base_price' => 999.00,
                'annual_price' => 9990.00,
                'user_limit' => 15,
                'location_limit' => 3,
                'employee_limit' => 200,
                'storage_limit_gb' => 25,
                'duration_months' => 12,
                'is_active' => true,
                'is_featured' => true,
                'sort_order' => 2,
                'features' => [
                    'Gelişmiş İK Yönetimi',
                    '15 Kullanıcı',
                    '3 Lokasyon',
                    '200 Personel',
                    '25 GB Depolama',
                    'Öncelikli Destek',
                    'API Erişimi',
                ],
                'modules' => ['core', 'job-applications', 'document-management', 'onboarding', 'leave-management'],
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Büyük kurumlar için sınırsız İK platformu.',
                'base_price' => 2499.00,
                'annual_price' => 24990.00,
                'user_limit' => 0, // Sınırsız
                'location_limit' => 0, // Sınırsız
                'employee_limit' => 0, // Sınırsız
                'storage_limit_gb' => 0, // Sınırsız
                'duration_months' => 12,
                'is_active' => true,
                'is_featured' => false,
                'sort_order' => 3,
                'features' => [
                    'Sınırsız İK Yönetimi',
                    'Sınırsız Kullanıcı',
                    'Sınırsız Lokasyon',
                    'Sınırsız Personel',
                    'Sınırsız Depolama',
                    '7/24 Destek',
                    'Özel Entegrasyonlar',
                    'Dedicated Account Manager',
                ],
                'modules' => 'all', // Tüm modüller
            ],
        ];

        foreach ($packages as $packageData) {
            $modulesSlugs = $packageData['modules'];
            unset($packageData['modules']);

            $package = LicensePackage::firstOrCreate(
                ['slug' => $packageData['slug']],
                $packageData
            );

            // Modülleri ekle
            if ($modulesSlugs === 'all') {
                $moduleIds = Module::pluck('id')->toArray();
            } else {
                // Core modülleri her zaman ekle
                $coreModuleIds = Module::where('is_core', true)->pluck('id')->toArray();
                $selectedModuleIds = Module::whereIn('slug', $modulesSlugs)->pluck('id')->toArray();
                $moduleIds = array_unique(array_merge($coreModuleIds, $selectedModuleIds));
            }

            $syncData = [];
            foreach ($moduleIds as $moduleId) {
                $syncData[$moduleId] = ['is_included' => true];
            }
            $package->modules()->sync($syncData);
        }

        $this->command->info('Lisans paketleri oluşturuldu: '.count($packages).' paket');
    }
}
