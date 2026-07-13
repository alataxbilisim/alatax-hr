<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Position;
use Illuminate\Support\Facades\DB;

/**
 * FAZ A5 — Firma pozisyon kataloğu + yaygın SGK/İŞKUR meslek kodları seed.
 *
 * Resmi açık API yok (İŞKUR Excel/PDF, TÜRMOB tablosu). Bu yüzden ~40 yaygın
 * ISCO-08 / İŞKUR tarzı kod gömülü; firma CRUD ile genişletebilir/düzenleyebilir.
 */
class PositionCatalogSeedService
{
    /**
     * Yaygın TR meslek / unvan tanımları.
     * code = firma içi kısa kod; sgk_occupation_code = İŞKUR/SGK meslek kodu.
     *
     * @return list<array{code: string, name: string, sgk_occupation_code: string, sort_order: int}>
     */
    public static function definitions(): array
    {
        $rows = [
            ['GEN_MUD', 'Genel Müdür', '1120.05'],
            ['IK_MUD', 'İnsan Kaynakları Müdürü', '1212.02'],
            ['IK_UZM', 'İnsan Kaynakları Uzmanı', '2423.01'],
            ['IK_ASY', 'İnsan Kaynakları Asistanı', '4416.01'],
            ['MAL_MUD', 'Mali İşler Müdürü', '1211.02'],
            ['MUH_UZM', 'Muhasebe Uzmanı', '2411.03'],
            ['MUH_YRD', 'Muhasebe Yardımcısı', '4311.02'],
            ['SAT_MUD', 'Satış Müdürü', '1221.02'],
            ['SAT_TEM', 'Satış Temsilcisi', '3322.01'],
            ['PAZ_UZM', 'Pazarlama Uzmanı', '2431.02'],
            ['MUS_HIZ', 'Müşteri Hizmetleri Temsilcisi', '4222.01'],
            ['IT_MUD', 'Bilgi Teknolojileri Müdürü', '1330.02'],
            ['YAZ_GEL', 'Yazılım Geliştirici', '2512.05'],
            ['YAZ_KID', 'Kıdemli Yazılım Geliştirici', '2512.05'],
            ['SIS_YON', 'Sistem Yöneticisi', '2522.02'],
            ['AG_UZM', 'Ağ Uzmanı', '2523.01'],
            ['VERI_AN', 'Veri Analisti', '2511.03'],
            ['PROJE_Y', 'Proje Yöneticisi', '1219.05'],
            ['URUN_Y', 'Ürün Yöneticisi', '1223.03'],
            ['TAS_GRA', 'Grafik Tasarımcı', '2166.02'],
            ['HUK_MUS', 'Hukuk Müşaviri', '2611.01'],
            ['AVUKAT', 'Avukat', '2611.02'],
            ['DOKTOR', 'Doktor / Hekim', '2211.02'],
            ['HEMSIRE', 'Hemşire', '2221.01'],
            ['OGRET', 'Öğretmen', '2330.02'],
            ['EGT_UZM', 'Eğitim Uzmanı', '2424.01'],
            ['ISG_UZM', 'İş Güvenliği Uzmanı', '2263.01'],
            ['KAL_KON', 'Kalite Kontrol Uzmanı', '2141.04'],
            ['URE_MUD', 'Üretim Müdürü', '1321.02'],
            ['URE_OPR', 'Üretim Operatörü', '8183.01'],
            ['DEPO_SOR', 'Depo Sorumlusu', '4321.01'],
            ['LOJ_UZM', 'Lojistik Uzmanı', '1324.02'],
            ['SOFOR', 'Şoför', '8322.01'],
            ['GUV_GOR', 'Güvenlik Görevlisi', '5414.01'],
            ['TEMIZ', 'Temizlik Görevlisi', '9112.01'],
            ['ASCI', 'Aşçı', '5120.01'],
            ['GARSON', 'Garson', '5131.01'],
            ['RESEPS', 'Resepsiyonist', '4224.01'],
            ['SEKRETER', 'Sekreter', '4120.01'],
            ['IDARI_I', 'İdari İşler Uzmanı', '3343.01'],
            ['SATIN_A', 'Satın Alma Uzmanı', '3323.01'],
            ['FIN_UZM', 'Finans Uzmanı', '2412.01'],
            ['IC_DEN', 'İç Denetçi', '2411.05'],
            ['ARGE_MU', 'Ar-Ge Mühendisi', '2149.05'],
            ['INSA_MU', 'İnşaat Mühendisi', '2142.01'],
            ['MAK_MU', 'Makine Mühendisi', '2144.01'],
            ['ELEK_MU', 'Elektrik Mühendisi', '2151.01'],
            ['ISLET_M', 'İşletme Müdürü', '1219.02'],
        ];

        $out = [];
        foreach ($rows as $i => [$code, $name, $sgk]) {
            $out[] = [
                'code' => $code,
                'name' => $name,
                'sgk_occupation_code' => $sgk,
                'sort_order' => ($i + 1) * 10,
            ];
        }

        return $out;
    }

    public function ensureForCompany(Company|int $company): void
    {
        $companyId = $company instanceof Company ? (int) $company->id : $company;

        DB::transaction(function () use ($companyId): void {
            foreach (self::definitions() as $def) {
                $existing = Position::withoutCompanyScope()
                    ->where('company_id', $companyId)
                    ->where('code', $def['code'])
                    ->first();

                if ($existing) {
                    // K-A: name etiketi firma özelleştirmiş olabilir — dokunulmaz
                    $existing->forceFill([
                        'is_system' => true,
                        'sgk_occupation_code' => $existing->sgk_occupation_code ?: $def['sgk_occupation_code'],
                        'is_active' => $existing->is_active,
                    ])->save();

                    continue;
                }

                Position::withoutCompanyScope()->create([
                    'company_id' => $companyId,
                    'code' => $def['code'],
                    'name' => $def['name'],
                    'sgk_occupation_code' => $def['sgk_occupation_code'],
                    'department_id' => null,
                    'description' => null,
                    'is_active' => true,
                    'is_system' => true,
                    'sort_order' => $def['sort_order'],
                ]);
            }
        });
    }

    public function ensureForAllCompanies(): int
    {
        $count = 0;
        Company::query()->orderBy('id')->each(function (Company $company) use (&$count): void {
            $this->ensureForCompany($company);
            $count++;
        });

        return $count;
    }
}
