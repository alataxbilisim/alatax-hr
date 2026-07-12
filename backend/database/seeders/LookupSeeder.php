<?php

namespace Database\Seeders;

use App\Models\Lookup;
use App\Services\LookupService;
use Illuminate\Database\Seeder;

/**
 * Lookup Engine seed — idempotent.
 * Sistem: currency, city_tr, blood_type, country.
 * Firma default: employee_*, contract, work_type…
 * Hibrit: leave_request_status (meta.hybrid).
 */
class LookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedEmployeeStatus();
        $this->seedWorkType();
        $this->seedGender();
        $this->seedMaritalStatus();
        $this->seedEducationLevel();
        $this->seedEmergencyRelation();
        $this->seedContractType();
        $this->seedEmployeeDocumentCategory();
        $this->seedLeaveRequestStatus();
        $this->seedLeaveGenderRestriction();
        $this->seedHolidayType();
        $this->seedCurrency();
        $this->seedBloodType();
        $this->seedCountries();
        $this->seedCitiesTr();
    }

    private function seedEmployeeStatus(): void
    {
        foreach ([
            ['value' => 'active', 'label' => 'Aktif', 'color' => '#10b981', 'sort_order' => 10],
            ['value' => 'on_leave', 'label' => 'İzinli', 'color' => '#f59e0b', 'sort_order' => 20],
            ['value' => 'suspended', 'label' => 'Askıda', 'color' => '#ef4444', 'sort_order' => 30],
            ['value' => 'terminated', 'label' => 'İşten Çıkmış', 'color' => '#64748b', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMPLOYEE_STATUS, $item, false);
        }
    }

    private function seedWorkType(): void
    {
        foreach ([
            ['value' => 'full_time', 'label' => 'Tam Zamanlı', 'sort_order' => 10],
            ['value' => 'part_time', 'label' => 'Yarı Zamanlı', 'sort_order' => 20],
            ['value' => 'remote', 'label' => 'Uzaktan', 'sort_order' => 30],
            ['value' => 'hybrid', 'label' => 'Hibrit', 'sort_order' => 40],
            ['value' => 'contract', 'label' => 'Sözleşmeli', 'sort_order' => 50],
            ['value' => 'internship', 'label' => 'Stajyer', 'sort_order' => 60],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_WORK_TYPE, $item, false);
        }
    }

    private function seedGender(): void
    {
        foreach ([
            ['value' => 'male', 'label' => 'Erkek', 'sort_order' => 10],
            ['value' => 'female', 'label' => 'Kadın', 'sort_order' => 20],
            ['value' => 'other', 'label' => 'Diğer', 'sort_order' => 30],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_GENDER, $item, false);
        }
    }

    private function seedMaritalStatus(): void
    {
        foreach ([
            ['value' => 'single', 'label' => 'Bekar', 'sort_order' => 10],
            ['value' => 'married', 'label' => 'Evli', 'sort_order' => 20],
            ['value' => 'divorced', 'label' => 'Boşanmış', 'sort_order' => 30],
            ['value' => 'widowed', 'label' => 'Dul', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_MARITAL_STATUS, $item, false);
        }
    }

    private function seedEducationLevel(): void
    {
        // Mevcut employee kayıtları TR etiketi value olarak tutuyor — K-A uyumu
        foreach ([
            ['value' => 'İlkokul', 'label' => 'İlkokul', 'sort_order' => 10],
            ['value' => 'Ortaokul', 'label' => 'Ortaokul', 'sort_order' => 20],
            ['value' => 'Lise', 'label' => 'Lise', 'sort_order' => 30],
            ['value' => 'Önlisans', 'label' => 'Önlisans', 'sort_order' => 40],
            ['value' => 'Lisans', 'label' => 'Lisans', 'sort_order' => 50],
            ['value' => 'Yüksek Lisans', 'label' => 'Yüksek Lisans', 'sort_order' => 60],
            ['value' => 'Doktora', 'label' => 'Doktora', 'sort_order' => 70],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EDUCATION_LEVEL, $item, false);
        }
    }

    private function seedEmergencyRelation(): void
    {
        foreach ([
            ['value' => 'Eş', 'label' => 'Eş', 'sort_order' => 10],
            ['value' => 'Anne', 'label' => 'Anne', 'sort_order' => 20],
            ['value' => 'Baba', 'label' => 'Baba', 'sort_order' => 30],
            ['value' => 'Kardeş', 'label' => 'Kardeş', 'sort_order' => 40],
            ['value' => 'Çocuk', 'label' => 'Çocuk', 'sort_order' => 50],
            ['value' => 'Arkadaş', 'label' => 'Arkadaş', 'sort_order' => 60],
            ['value' => 'Diğer', 'label' => 'Diğer', 'sort_order' => 70],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMERGENCY_RELATION, $item, false);
        }
    }

    private function seedContractType(): void
    {
        foreach ([
            ['value' => 'permanent', 'label' => 'Süresiz (Belirsiz Süreli)', 'sort_order' => 10],
            ['value' => 'temporary', 'label' => 'Süreli (Belirli Süreli)', 'sort_order' => 20],
            ['value' => 'intern', 'label' => 'Stajyer', 'sort_order' => 30],
            ['value' => 'contract', 'label' => 'Sözleşmeli', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_CONTRACT_TYPE, $item, false);
        }
    }

    private function seedEmployeeDocumentCategory(): void
    {
        foreach ([
            ['value' => 'id_card', 'label' => 'Kimlik', 'sort_order' => 10],
            ['value' => 'contract', 'label' => 'Sözleşme', 'sort_order' => 20],
            ['value' => 'certificate', 'label' => 'Sertifika', 'sort_order' => 30],
            ['value' => 'education', 'label' => 'Eğitim', 'sort_order' => 40],
            ['value' => 'health', 'label' => 'Sağlık', 'sort_order' => 50],
            ['value' => 'other', 'label' => 'Diğer', 'sort_order' => 60],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_EMPLOYEE_DOCUMENT_CATEGORY, $item, false);
        }
    }

    private function seedLeaveRequestStatus(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'pending', 'label' => 'Bekleyen', 'color' => '#f59e0b', 'sort_order' => 10],
            ['value' => 'approved', 'label' => 'Onaylanan', 'color' => '#10b981', 'sort_order' => 20],
            ['value' => 'rejected', 'label' => 'Reddedilen', 'color' => '#ef4444', 'sort_order' => 30],
            ['value' => 'cancelled', 'label' => 'İptal', 'color' => '#64748b', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_LEAVE_REQUEST_STATUS, $item, false, $meta);
        }
    }

    private function seedLeaveGenderRestriction(): void
    {
        $meta = ['hybrid' => true];
        foreach ([
            ['value' => 'all', 'label' => 'Herkes', 'sort_order' => 10],
            ['value' => 'male', 'label' => 'Erkek', 'sort_order' => 20],
            ['value' => 'female', 'label' => 'Kadın', 'sort_order' => 30],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_LEAVE_GENDER_RESTRICTION, $item, false, $meta);
        }
    }

    private function seedHolidayType(): void
    {
        foreach ([
            ['value' => 'national', 'label' => 'Resmi Tatil', 'sort_order' => 10],
            ['value' => 'religious', 'label' => 'Dini Tatil', 'sort_order' => 20],
            ['value' => 'company', 'label' => 'Şirket Tatili', 'sort_order' => 30],
            ['value' => 'regional', 'label' => 'Bölgesel', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_HOLIDAY_TYPE, $item, false);
        }
    }

    private function seedCurrency(): void
    {
        foreach ([
            ['value' => 'TRY', 'label' => 'TRY (₺)', 'sort_order' => 10],
            ['value' => 'USD', 'label' => 'USD ($)', 'sort_order' => 20],
            ['value' => 'EUR', 'label' => 'EUR (€)', 'sort_order' => 30],
            ['value' => 'GBP', 'label' => 'GBP (£)', 'sort_order' => 40],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_CURRENCY, $item, true);
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
            ], true);
        }
    }

    private function seedCountries(): void
    {
        foreach ([
            ['value' => 'TR', 'label' => 'Türkiye', 'sort_order' => 10],
            ['value' => 'DE', 'label' => 'Almanya', 'sort_order' => 20],
            ['value' => 'US', 'label' => 'Amerika Birleşik Devletleri', 'sort_order' => 30],
            ['value' => 'GB', 'label' => 'Birleşik Krallık', 'sort_order' => 40],
            ['value' => 'FR', 'label' => 'Fransa', 'sort_order' => 50],
            ['value' => 'NL', 'label' => 'Hollanda', 'sort_order' => 60],
            ['value' => 'AZ', 'label' => 'Azerbaycan', 'sort_order' => 70],
            ['value' => 'CY', 'label' => 'Kıbrıs', 'sort_order' => 80],
        ] as $item) {
            $this->upsertDefault(LookupService::TYPE_COUNTRY, $item, true);
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
            ], true);
        }
    }

    /**
     * @param  array{value: string, label: string, color?: ?string, sort_order: int}  $item
     * @param  array<string, mixed>|null  $meta
     */
    private function upsertDefault(string $type, array $item, bool $isSystem, ?array $meta = null): void
    {
        $row = Lookup::withTrashed()
            ->whereNull('company_id')
            ->where('lookup_type', $type)
            ->where('value', $item['value'])
            ->first();

        $payload = [
            'label' => $item['label'],
            'color' => $item['color'] ?? null,
            'sort_order' => $item['sort_order'],
            'is_active' => true,
            'is_system' => $isSystem,
            'parent_lookup_id' => null,
            'meta' => $meta,
            'deleted_at' => null,
        ];

        if ($row) {
            $row->fill($payload)->save();

            return;
        }

        Lookup::create(array_merge([
            'company_id' => null,
            'lookup_type' => $type,
            'value' => $item['value'],
        ], $payload));
    }
}
