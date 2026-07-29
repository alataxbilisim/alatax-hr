<?php

namespace App\Services\Kvkk;

use App\Services\Reports\ReportField;

/**
 * D2a — Veri kategorileri kataloğu.
 * D1e ReportField hassasiyet sınıflandırmasının ÜSTÜNE kurulur; ikinci sistem açılmaz.
 *
 * Her kategori: table.field grupları + D1e sensitivity etiketi.
 */
final class KvkkDataCategoryCatalog
{
    /**
     * @return list<array{
     *   key: string,
     *   label: string,
     *   sensitivity: string,
     *   fields: list<array{table: string, column: string, label: string}>
     * }>
     */
    public static function all(): array
    {
        return [
            [
                'key' => 'identity',
                'label' => 'Kimlik (TCKN, ad, doğum)',
                'sensitivity' => ReportField::SENSITIVITY_PERSONAL,
                'fields' => [
                    ['table' => 'employees', 'column' => 'national_id', 'label' => 'TCKN'],
                    ['table' => 'employees', 'column' => 'birth_date', 'label' => 'Doğum tarihi'],
                    ['table' => 'job_applications', 'column' => 'first_name', 'label' => 'Ad'],
                    ['table' => 'job_applications', 'column' => 'last_name', 'label' => 'Soyad'],
                ],
            ],
            [
                'key' => 'contact',
                'label' => 'İletişim (telefon, e-posta, adres)',
                'sensitivity' => ReportField::SENSITIVITY_PERSONAL,
                'fields' => [
                    ['table' => 'employees', 'column' => 'personal_phone', 'label' => 'Telefon'],
                    ['table' => 'employees', 'column' => 'personal_email', 'label' => 'E-posta'],
                    ['table' => 'employees', 'column' => 'address', 'label' => 'Adres'],
                    ['table' => 'job_applications', 'column' => 'email', 'label' => 'Aday e-posta'],
                    ['table' => 'job_applications', 'column' => 'phone', 'label' => 'Aday telefon'],
                ],
            ],
            [
                'key' => 'health_special',
                'label' => 'Sağlık / özel nitelikli',
                'sensitivity' => ReportField::SENSITIVITY_SPECIAL,
                'fields' => [
                    ['table' => 'employees', 'column' => 'blood_type', 'label' => 'Kan grubu'],
                    ['table' => 'leave_requests', 'column' => 'reason', 'label' => 'İzin gerekçesi'],
                    ['table' => 'employee_documents', 'column' => 'category', 'label' => 'Belge kategorisi (sağlık)'],
                ],
            ],
            [
                'key' => 'financial',
                'label' => 'Mali (maaş, IBAN, banka)',
                'sensitivity' => ReportField::SENSITIVITY_PERSONAL,
                'fields' => [
                    ['table' => 'employees', 'column' => 'gross_salary', 'label' => 'Brüt maaş'],
                    ['table' => 'employees', 'column' => 'iban', 'label' => 'IBAN'],
                    ['table' => 'employees', 'column' => 'bank_name', 'label' => 'Banka'],
                    ['table' => 'payslips', 'column' => 'net_salary', 'label' => 'Bordro net'],
                ],
            ],
            [
                'key' => 'location',
                'label' => 'Konum / cihaz (PDKS)',
                'sensitivity' => ReportField::SENSITIVITY_PERSONAL,
                'fields' => [
                    ['table' => 'attendance_records', 'column' => 'clock_in_latitude', 'label' => 'Giriş enlem'],
                    ['table' => 'attendance_records', 'column' => 'clock_in_longitude', 'label' => 'Giriş boylam'],
                    ['table' => 'attendance_records', 'column' => 'clock_in_ip', 'label' => 'Giriş IP'],
                ],
            ],
            [
                'key' => 'cv_recruitment',
                'label' => 'İşe alım / CV',
                'sensitivity' => ReportField::SENSITIVITY_PERSONAL,
                'fields' => [
                    ['table' => 'job_applications', 'column' => 'cv_path', 'label' => 'CV dosyası'],
                    ['table' => 'job_applications', 'column' => 'form_data', 'label' => 'Başvuru form verisi'],
                ],
            ],
            [
                'key' => 'survey_answers',
                'label' => 'Anket cevapları',
                'sensitivity' => ReportField::SENSITIVITY_ANONYMOUS,
                'fields' => [
                    ['table' => 'survey_responses', 'column' => 'answer_text', 'label' => 'Metin cevap'],
                    ['table' => 'survey_responses', 'column' => 'answer_numeric', 'label' => 'Sayısal cevap'],
                ],
            ],
            [
                'key' => 'family_emergency',
                'label' => 'Aile / acil durum',
                'sensitivity' => ReportField::SENSITIVITY_PERSONAL,
                'fields' => [
                    ['table' => 'employees', 'column' => 'emergency_contact_name', 'label' => 'Acil kişi'],
                    ['table' => 'employees', 'column' => 'emergency_contact_phone', 'label' => 'Acil telefon'],
                ],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(static fn (array $c): string => $c['key'], self::all());
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function keyed(): array
    {
        $out = [];
        foreach (self::all() as $c) {
            $out[$c['key']] = $c;
        }

        return $out;
    }
}
