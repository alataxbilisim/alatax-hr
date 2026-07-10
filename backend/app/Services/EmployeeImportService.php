<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeImportService
{
    protected int $companyId;

    protected int $userId;

    protected array $errors = [];

    protected array $successRows = [];

    protected array $failedRows = [];

    protected array $departments = [];

    public function __construct(int $companyId, int $userId)
    {
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->loadDepartments();
    }

    /**
     * Departmanları önbelleğe al
     */
    protected function loadDepartments(): void
    {
        $this->departments = Department::where('company_id', $this->companyId)
            ->pluck('id', 'name')
            ->toArray();
    }

    /**
     * CSV/Excel dosyasını import et
     */
    public function import(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, ['csv', 'xlsx', 'xls'])) {
            return [
                'success' => false,
                'message' => 'Geçersiz dosya formatı. CSV, XLSX veya XLS formatında olmalıdır.',
                'data' => null,
            ];
        }

        try {
            $rows = $this->parseFile($file, $extension);

            if (empty($rows)) {
                return [
                    'success' => false,
                    'message' => 'Dosyada veri bulunamadı.',
                    'data' => null,
                ];
            }

            $this->processRows($rows);

            return [
                'success' => true,
                'message' => 'Import işlemi tamamlandı.',
                'data' => [
                    'total' => count($rows),
                    'success_count' => count($this->successRows),
                    'failed_count' => count($this->failedRows),
                    'success_rows' => $this->successRows,
                    'failed_rows' => $this->failedRows,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Import sırasında hata oluştu: '.$e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Dosyayı parse et
     */
    protected function parseFile(UploadedFile $file, string $extension): array
    {
        if ($extension === 'csv') {
            return $this->parseCsv($file);
        }

        // Excel için PhpSpreadsheet kullan
        return $this->parseExcel($file);
    }

    /**
     * CSV dosyasını parse et
     */
    protected function parseCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getPathname(), 'r');

        // BOM karakterini kontrol et
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $headers = fgetcsv($handle, 0, ';');
        if (! $headers) {
            $headers = fgetcsv($handle, 0, ',');
        }

        $headers = array_map('trim', $headers);
        $headerMap = $this->mapHeaders($headers);

        while (($data = fgetcsv($handle, 0, ';')) !== false) {
            if (count($data) === 1 && strpos($data[0], ',') !== false) {
                $data = str_getcsv($data[0], ',');
            }

            $row = [];
            foreach ($headerMap as $field => $index) {
                $row[$field] = isset($data[$index]) ? trim($data[$index]) : null;
            }

            if (! empty(array_filter($row))) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    /**
     * Excel dosyasını parse et
     */
    protected function parseExcel(UploadedFile $file): array
    {
        $rows = [];

        // PhpSpreadsheet kütüphanesi varsa kullan
        if (class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            if (empty($data)) {
                return [];
            }

            $headers = array_map('trim', $data[0]);
            $headerMap = $this->mapHeaders($headers);

            for ($i = 1; $i < count($data); $i++) {
                $row = [];
                foreach ($headerMap as $field => $index) {
                    $row[$field] = isset($data[$i][$index]) ? trim($data[$i][$index]) : null;
                }

                if (! empty(array_filter($row))) {
                    $rows[] = $row;
                }
            }
        } else {
            // Basit CSV fallback
            return $this->parseCsv($file);
        }

        return $rows;
    }

    /**
     * Header'ları alan adlarına eşle
     */
    protected function mapHeaders(array $headers): array
    {
        $mapping = [
            'sicil no' => 'employee_code',
            'sicil_no' => 'employee_code',
            'employee_code' => 'employee_code',
            'ad soyad' => 'name',
            'ad_soyad' => 'name',
            'name' => 'name',
            'email' => 'personal_email',
            'e-posta' => 'personal_email',
            'personal_email' => 'personal_email',
            'telefon' => 'personal_phone',
            'phone' => 'personal_phone',
            'personal_phone' => 'personal_phone',
            'departman' => 'department',
            'department' => 'department',
            'pozisyon' => 'position',
            'position' => 'position',
            'unvan' => 'title',
            'ünvan' => 'title',
            'title' => 'title',
            'işe giriş' => 'hire_date',
            'ise giris' => 'hire_date',
            'hire_date' => 'hire_date',
            'durum' => 'status',
            'status' => 'status',
            'cinsiyet' => 'gender',
            'gender' => 'gender',
            'doğum tarihi' => 'birth_date',
            'dogum tarihi' => 'birth_date',
            'birth_date' => 'birth_date',
            'tc kimlik' => 'national_id',
            'tc kimlik no' => 'national_id',
            'national_id' => 'national_id',
            'adres' => 'address',
            'address' => 'address',
            'il' => 'city',
            'city' => 'city',
            'ilçe' => 'district',
            'ilce' => 'district',
            'district' => 'district',
            'sözleşme tipi' => 'contract_type',
            'sozlesme tipi' => 'contract_type',
            'contract_type' => 'contract_type',
            'çalışma tipi' => 'work_type',
            'calisma tipi' => 'work_type',
            'work_type' => 'work_type',
        ];

        $result = [];
        foreach ($headers as $index => $header) {
            $normalizedHeader = mb_strtolower(trim($header));
            if (isset($mapping[$normalizedHeader])) {
                $result[$mapping[$normalizedHeader]] = $index;
            }
        }

        return $result;
    }

    /**
     * Satırları işle
     */
    protected function processRows(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Header dahil satır numarası

            try {
                $this->processRow($row, $rowNumber);
            } catch (\Exception $e) {
                $this->failedRows[] = [
                    'row' => $rowNumber,
                    'data' => $row,
                    'error' => $e->getMessage(),
                ];
            }
        }
    }

    /**
     * Tek bir satırı işle
     */
    protected function processRow(array $row, int $rowNumber): void
    {
        // Validasyon
        $validator = Validator::make($row, [
            'employee_code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'personal_email' => 'nullable|email|max:255',
            'personal_phone' => 'nullable|string|max:20',
            'position' => 'nullable|string|max:100',
            'title' => 'nullable|string|max:100',
            'hire_date' => 'nullable|date',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'status' => 'nullable|in:active,on_leave,suspended,terminated',
            'contract_type' => 'nullable|in:permanent,temporary,intern,contract',
            'work_type' => 'nullable|in:full_time,part_time,remote,hybrid',
        ]);

        if ($validator->fails()) {
            throw new \Exception(implode(', ', $validator->errors()->all()));
        }

        // Sicil no benzersizlik kontrolü
        $existingEmployee = Employee::where('company_id', $this->companyId)
            ->where('employee_code', $row['employee_code'])
            ->first();

        // Departman ID'sini bul
        $departmentId = null;
        if (! empty($row['department'])) {
            $departmentId = $this->departments[$row['department']] ?? null;

            // Departman yoksa oluştur
            if (! $departmentId) {
                $department = Department::create([
                    'company_id' => $this->companyId,
                    'name' => $row['department'],
                    'is_active' => true,
                    'created_by' => $this->userId,
                ]);
                $departmentId = $department->id;
                $this->departments[$row['department']] = $departmentId;
            }
        }

        // Tarih formatlarını düzelt
        $hireDate = $this->parseDate($row['hire_date'] ?? null);
        $birthDate = $this->parseDate($row['birth_date'] ?? null);

        // Status eşlemesi
        $statusMap = [
            'aktif' => 'active',
            'izinli' => 'on_leave',
            'askıda' => 'suspended',
            'işten çıkmış' => 'terminated',
            'passive' => 'terminated',
        ];
        $status = $row['status'] ?? 'active';
        $status = $statusMap[mb_strtolower($status)] ?? $status;

        // Gender eşlemesi
        $genderMap = [
            'erkek' => 'male',
            'kadın' => 'female',
            'kadin' => 'female',
        ];
        $gender = $row['gender'] ?? null;
        $gender = $gender ? ($genderMap[mb_strtolower($gender)] ?? $gender) : null;

        DB::beginTransaction();
        try {
            if ($existingEmployee) {
                // Güncelle
                $existingEmployee->update([
                    'department_id' => $departmentId,
                    'title' => $row['title'] ?? $existingEmployee->title,
                    'position' => $row['position'] ?? $existingEmployee->position,
                    'birth_date' => $birthDate ?? $existingEmployee->birth_date,
                    'national_id' => $row['national_id'] ?? $existingEmployee->national_id,
                    'gender' => $gender ?? $existingEmployee->gender,
                    'personal_email' => $row['personal_email'] ?? $existingEmployee->personal_email,
                    'personal_phone' => $row['personal_phone'] ?? $existingEmployee->personal_phone,
                    'address' => $row['address'] ?? $existingEmployee->address,
                    'city' => $row['city'] ?? $existingEmployee->city,
                    'district' => $row['district'] ?? $existingEmployee->district,
                    'hire_date' => $hireDate ?? $existingEmployee->hire_date,
                    'contract_type' => $row['contract_type'] ?? $existingEmployee->contract_type,
                    'work_type' => $row['work_type'] ?? $existingEmployee->work_type,
                    'status' => $status,
                    'updated_by' => $this->userId,
                ]);

                // User adını güncelle
                if ($existingEmployee->user && ! empty($row['name'])) {
                    $existingEmployee->user->update(['name' => $row['name']]);
                }

                $this->successRows[] = [
                    'row' => $rowNumber,
                    'employee_code' => $row['employee_code'],
                    'action' => 'updated',
                ];
            } else {
                // Yeni oluştur
                $employee = Employee::create([
                    'company_id' => $this->companyId,
                    'employee_code' => $row['employee_code'],
                    'department_id' => $departmentId,
                    'title' => $row['title'] ?? null,
                    'position' => $row['position'] ?? null,
                    'birth_date' => $birthDate,
                    'national_id' => $row['national_id'] ?? null,
                    'gender' => $gender,
                    'personal_email' => $row['personal_email'] ?? null,
                    'personal_phone' => $row['personal_phone'] ?? null,
                    'address' => $row['address'] ?? null,
                    'city' => $row['city'] ?? null,
                    'district' => $row['district'] ?? null,
                    'hire_date' => $hireDate,
                    'contract_type' => $row['contract_type'] ?? null,
                    'work_type' => $row['work_type'] ?? null,
                    'status' => $status,
                    'created_by' => $this->userId,
                ]);

                // Ad Soyad varsa User oluştur (portal erişimi olmadan)
                // Bu opsiyonel - yönetici isterse sonra portal erişimi verebilir

                $this->successRows[] = [
                    'row' => $rowNumber,
                    'employee_code' => $row['employee_code'],
                    'action' => 'created',
                ];
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Tarih parse et
     */
    protected function parseDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        // Farklı formatları dene
        $formats = [
            'Y-m-d',
            'd.m.Y',
            'd/m/Y',
            'm/d/Y',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        // strtotime ile dene
        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    /**
     * Import şablonunu oluştur
     */
    public static function generateTemplate(): string
    {
        $headers = [
            'Sicil No',
            'Ad Soyad',
            'Email',
            'Telefon',
            'Departman',
            'Pozisyon',
            'Ünvan',
            'İşe Giriş',
            'Durum',
            'Cinsiyet',
            'Doğum Tarihi',
            'TC Kimlik No',
            'Adres',
            'İl',
            'İlçe',
            'Sözleşme Tipi',
            'Çalışma Tipi',
        ];

        $sampleData = [
            [
                'EMP001',
                'Ahmet Yılmaz',
                'ahmet@example.com',
                '5551234567',
                'Yazılım',
                'Developer',
                'Kıdemli Yazılım Mühendisi',
                '01.01.2024',
                'active',
                'male',
                '15.05.1990',
                '12345678901',
                'Örnek Mah. Test Sk. No:1',
                'İstanbul',
                'Kadıköy',
                'permanent',
                'full_time',
            ],
        ];

        // CSV oluştur
        $output = chr(0xEF).chr(0xBB).chr(0xBF); // BOM
        $output .= implode(';', $headers)."\n";

        foreach ($sampleData as $row) {
            $output .= implode(';', $row)."\n";
        }

        return $output;
    }
}
