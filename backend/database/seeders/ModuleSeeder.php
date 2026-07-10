<?php

namespace Database\Seeders;

use App\Models\Module;
use Illuminate\Database\Seeder;

class ModuleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $modules = [
            // Core Modüller (Her zaman aktif)
            [
                'name' => 'Kullanıcı Yönetimi',
                'slug' => 'user-management',
                'description' => 'Kullanıcı ve rol yönetimi, yetkilendirme işlemleri',
                'icon' => 'bi-people',
                'is_core' => true,
                'price_monthly' => 0,
                'price_yearly' => 0,
                'sort_order' => 1,
            ],
            [
                'name' => 'Firma Yönetimi',
                'slug' => 'company-management',
                'description' => 'Firma bilgileri ve ayarları yönetimi',
                'icon' => 'bi-building',
                'is_core' => true,
                'price_monthly' => 0,
                'price_yearly' => 0,
                'sort_order' => 2,
            ],
            [
                'name' => 'Log & Denetim',
                'slug' => 'audit-logs',
                'description' => 'Sistem ve kullanıcı işlem logları',
                'icon' => 'bi-journal-text',
                'is_core' => true,
                'price_monthly' => 0,
                'price_yearly' => 0,
                'sort_order' => 3,
            ],
            
            // Satılabilir Modüller
            [
                'name' => 'İş Başvuru & CV Havuzu',
                'slug' => 'job-applications',
                'description' => 'İş ilanları, başvuru formları, CV havuzu ve aday yönetimi',
                'icon' => 'bi-person-badge',
                'is_core' => false,
                'price_monthly' => 299,
                'price_yearly' => 2990,
                'sort_order' => 10,
            ],
            [
                'name' => 'Evrak Yönetimi',
                'slug' => 'document-management',
                'description' => 'Personel evrakları, doküman arşivi ve versiyonlama',
                'icon' => 'bi-file-earmark-text',
                'is_core' => false,
                'price_monthly' => 199,
                'price_yearly' => 1990,
                'sort_order' => 11,
            ],
            [
                'name' => 'Onboarding',
                'slug' => 'onboarding',
                'description' => 'İşe alım süreci, görev takibi ve otomatik bildirimler',
                'icon' => 'bi-person-check',
                'is_core' => false,
                'price_monthly' => 249,
                'price_yearly' => 2490,
                'sort_order' => 12,
            ],
            [
                'name' => 'İzin Yönetimi',
                'slug' => 'leave-management',
                'description' => 'İzin talepleri, onay akışları ve takvim',
                'icon' => 'bi-calendar-check',
                'is_core' => false,
                'price_monthly' => 199,
                'price_yearly' => 1990,
                'sort_order' => 13,
            ],
            [
                'name' => 'Performans Değerlendirme',
                'slug' => 'performance',
                'description' => 'Performans değerlendirme formları ve raporları',
                'icon' => 'bi-graph-up',
                'is_core' => false,
                'price_monthly' => 349,
                'price_yearly' => 3490,
                'sort_order' => 14,
            ],
            [
                'name' => 'Eğitim Yönetimi',
                'slug' => 'training',
                'description' => 'Eğitim planları, katılım takibi ve sertifikalar',
                'icon' => 'bi-mortarboard',
                'is_core' => false,
                'price_monthly' => 249,
                'price_yearly' => 2490,
                'sort_order' => 15,
            ],
            [
                'name' => 'Varlık Yönetimi',
                'slug' => 'asset-management',
                'description' => 'Demirbaş takibi, zimmet ve iade işlemleri',
                'icon' => 'bi-laptop',
                'is_core' => false,
                'price_monthly' => 149,
                'price_yearly' => 1490,
                'sort_order' => 16,
            ],
            
            // Yeni Modüller (Global Standart)
            [
                'name' => 'İK Analitiği',
                'slug' => 'hr-analytics',
                'description' => 'Workforce analytics, turnover analizi, raporlama dashboardları',
                'icon' => 'bi-bar-chart-line',
                'is_core' => false,
                'price_monthly' => 399,
                'price_yearly' => 3990,
                'sort_order' => 17,
            ],
            [
                'name' => 'Anket & Geri Bildirim',
                'slug' => 'surveys',
                'description' => 'Çalışan memnuniyeti anketleri, eNPS, pulse surveys',
                'icon' => 'bi-clipboard-data',
                'is_core' => false,
                'price_monthly' => 199,
                'price_yearly' => 1990,
                'sort_order' => 18,
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['slug' => $module['slug']],
                $module
            );
        }
    }
}

