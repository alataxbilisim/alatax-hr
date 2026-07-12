<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Services\LookupService;
use Illuminate\Database\Seeder;

/**
 * Lookup Engine seed — idempotent (updateOrCreate).
 * Sistem: currency, city_tr, blood_type, country.
 * Firma default (company_id=null, is_system=false): employee_status, work_type.
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEmployeeStatus();
        $this->seedWorkType();
        $this->seedCurrency();
        $this->seedBloodType();
        $this->seedCountries();
        $this->seedCitiesTr();
    }

    private function seedEmployeeStatus(): void
    {
        $items = [
            ['value' => 'active', 'label' => 'Aktif', 'color' => '#10b981', 'sort_order' => 10],
            ['value' => 'on_leave', 'label' => 'İzinli', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'suspended', 'label' => 'Askıda', 'color' => '#ef4444', 'sort_order' => 30],
            ['value' => 'terminated', 'label' => 'İşten Çıkmış', 'color' => '#64748b', 'sort_order' => 40],
        ];

        foreach ($items as $item) {
            $this->upsertDefault(LookupService::TYPE_EMPLOYEE_STATUS, $item, isSystem: false);
        }
    }

    private function seedWorkType(): void
    {
        $items = [
            ['value' => 'full_time', 'label' => 'Tam Zamanlı', 'color' => null, 'sort_order' => 10],
            ['value' => 'part_time', 'label' => 'Yarı Zamanlı', 'color' => null, 'sort_order' => 20],
            ['value' => 'remote', 'label' => 'Uzaktan', 'color' => null, 'sort_order' => 30],
            ['value' => 'hybrid', 'label' => 'Hibrit', 'color' => null, 'sort_order' => 40],
            ['value' => 'contract', 'label' => 'Sözleşmeli', 'color' => null, 'sort_order' => 50],
            ['value' => 'internship', 'label' => 'Stajyer', 'color' => null, 'sort_order' => 60],
        ];

        foreach ($items as $item) {
            $this->upsertDefault(LookupService::TYPE_WORK_TYPE, $item, isSystem: false);
        }
    }

    private function seedCurrency(): void
    {
        $items = [
            ['value' => 'TRY', 'label' => 'TRY (₺)', 'sort_order' => 10],
            ['value' => 'USD', 'label' => 'USD ($)', 'sort_order' => 20],
            ['value' => 'EUR', 'label' => 'EUR (€)', 'sort_order' => 30],
            ['value' => 'GBP', 'label' => 'GBP (£)', 'sort_order' => 40],
        ];

        foreach ($items as $item) {
            $this->upsertDefault(LookupService::TYPE_CURRENCY, $item, isSystem: true);
        }
    }

    private function seedBloodType(): void
    {
        $types = ['A Rh+', 'A Rh-', 'B Rh+', 'B Rh-', 'AB Rh+', 'AB Rh-', '0 Rh+', '0 Rh-'];
        foreach ($types as $i => $type) {
            $this->upsertDefault(LookupService::TYPE_BLOOD_TYPE, [
                'value' => $type,
                'label' => $type,
                'sort_order' => ($i + 1) * 10,
            ], isSystem: true);
        }
    }

    private function seedCountries(): void
    {
        $items = [
            ['value' => 'TR', 'label' => 'Türkiye', 'sort_order' => 10],
            ['value' => 'DE', 'label' => 'Almanya', 'sort_order' => 20],
            ['value' => 'US', 'label' => 'Amerika Birleşik Devletleri', 'sort_order' => 30],
            ['value' => 'GB', 'label' => 'Birleşik Krallık', 'sort_order' => 40],
            ['value' => 'FR', 'label' => 'Fransa', 'sort_order' => 50],
            ['value' => 'NL', 'label' => 'Hollanda', 'sort_order' => 60],
            ['value' => 'AZ', 'label' => 'Azerbaycan', 'sort_order' => 70],
            ['value' => 'CY', 'label' => 'Kıbrıs', 'sort_order' => 80],
        ];

        foreach ($items as $item) {
            $this->upsertDefault(LookupService::TYPE_COUNTRY, $item, isSystem: true);
        }
    }

    private function seedCitiesTr(): void
    {
        $cities = [
            'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara', 'Antalya',
            'Ardahan', 'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman', 'Bayburt', 'Bilecik',
            'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa', 'Çanakkale', 'Çankırı', 'Çorum',
            'Denizli', 'Diyarbakır', 'Düzce', 'Edirne', 'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir',
            'Gaziantep', 'Giresun', 'Gümüşhane', 'Hakkari', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul',
            'İzmir', 'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu', 'Kayseri', 'Kırıkkale',
            'Kırklareli', 'Kırşehir', 'Kilis', 'Kocaeli', 'Konya', 'Kütahya', 'Malatya', 'Manisa',
            'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde', 'Ordu', 'Osmaniye',
            'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas', 'Şanlıurfa', 'Şırnak',
            'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak', 'Van', 'Yalova', 'Yozgat', 'Zonguldak',
        ];

        foreach ($cities as $i => $city) {
            $this->upsertDefault(LookupService::TYPE_CITY_TR, [
                'value' => $city,
                'label' => $city,
                'sort_order' => ($i + 1) * 10,
            ], isSystem: true);
        }
    }

    /**
     * @param  array{value: string, label: string, color?: ?string, sort_order: int}  $item
     */
    private function upsertDefault(string $type, array $item, bool $isSystem): void
    {
        Lookup::withTrashed()->updateOrCreate(
            [
                'company_id' => null,
                'lookup_type' => $type,
                'value' => $item['value'],
            ],
            [
                'label' => $item['label'],
                'color' => $item['color'] ?? null,
                'sort_order' => $item['sort_order'],
                'is_active' => true,
                'is_system' => $isSystem,
                'parent_lookup_id' => null,
                'meta' => null,
                'deleted_at' => null,
            ]
        );
    }
}
