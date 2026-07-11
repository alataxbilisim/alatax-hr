<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Department;
use App\Models\Employee;
use App\Models\SavedReport;
use App\Services\EmployeeSensitiveFieldService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EmployeeReportController extends BaseController
{
    public function __construct(
        protected EmployeeSensitiveFieldService $sensitiveFields,
    ) {}

    /**
     * Desteklenen boyutlar (dimensions)
     */
    private array $dimensions = [
        'department' => ['field' => 'department_id', 'label' => 'Departman'],
        'position' => ['field' => 'position', 'label' => 'Pozisyon'],
        'contract_type' => ['field' => 'contract_type', 'label' => 'Sözleşme Tipi'],
        'work_type' => ['field' => 'work_type', 'label' => 'Çalışma Tipi'],
        'gender' => ['field' => 'gender', 'label' => 'Cinsiyet'],
        'marital_status' => ['field' => 'marital_status', 'label' => 'Medeni Durum'],
        'education_level' => ['field' => 'education_level', 'label' => 'Eğitim Seviyesi'],
        'city' => ['field' => 'city', 'label' => 'Şehir'],
        'status' => ['field' => 'status', 'label' => 'Durum'],
        'hire_year' => ['field' => 'hire_date', 'label' => 'İşe Alım Yılı', 'type' => 'year'],
        'hire_month' => ['field' => 'hire_date', 'label' => 'İşe Alım Ayı', 'type' => 'month'],
        'age_group' => ['field' => 'birth_date', 'label' => 'Yaş Grubu', 'type' => 'age_group'],
        'seniority_group' => ['field' => 'hire_date', 'label' => 'Kıdem Grubu', 'type' => 'seniority_group'],
    ];

    /**
     * Desteklenen metrikler (measures)
     */
    private array $measures = [
        'count' => ['label' => 'Personel Sayısı', 'aggregate' => 'count'],
        'avg_age' => ['label' => 'Ortalama Yaş', 'aggregate' => 'avg', 'requires_birth_date' => true],
        'avg_seniority' => ['label' => 'Ortalama Kıdem (Yıl)', 'aggregate' => 'avg', 'requires_hire_date' => true],
        'hire_count' => ['label' => 'İşe Alım Sayısı', 'aggregate' => 'count', 'filter' => 'recent_hires'],
        'termination_count' => ['label' => 'Ayrılma Sayısı', 'aggregate' => 'count', 'filter' => 'terminated'],
        'avg_gross_salary' => ['label' => 'Ortalama Brüt Maaş', 'aggregate' => 'avg', 'field' => 'gross_salary', 'requires_permission' => 'employees.salary.view'],
        'avg_net_salary' => ['label' => 'Ortalama Net Maaş', 'aggregate' => 'avg', 'field' => 'net_salary', 'requires_permission' => 'employees.salary.view'],
    ];

    /**
     * Rapor meta verilerini getir (boyutlar, metrikler, filtreler)
     */
    public function metadata(): JsonResponse
    {
        $departments = Department::where('company_id', $this->getCompanyId())
            ->where('is_active', true)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        $positions = Employee::where('company_id', $this->getCompanyId())
            ->whereNotNull('position')
            ->distinct()
            ->pluck('position')
            ->filter()
            ->values();

        $cities = Employee::where('company_id', $this->getCompanyId())
            ->whereNotNull('city')
            ->distinct()
            ->pluck('city')
            ->filter()
            ->values();

        return $this->success([
            'dimensions' => collect($this->dimensions)->map(fn ($d, $k) => [
                'value' => $k,
                'label' => $d['label'],
            ])->values(),
            'measures' => collect($this->measures)
                ->filter(function ($m) {
                    if (! isset($m['requires_permission'])) {
                        return true;
                    }

                    return $this->sensitiveFields->canViewSalary(auth()->user());
                })
                ->map(fn ($m, $k) => [
                    'value' => $k,
                    'label' => $m['label'],
                ])->values(),
            'chart_types' => [
                ['value' => 'bar', 'label' => 'Çubuk Grafik'],
                ['value' => 'horizontal_bar', 'label' => 'Yatay Çubuk Grafik'],
                ['value' => 'pie', 'label' => 'Pasta Grafik'],
                ['value' => 'donut', 'label' => 'Halka Grafik'],
                ['value' => 'line', 'label' => 'Çizgi Grafik'],
                ['value' => 'area', 'label' => 'Alan Grafik'],
                ['value' => 'heatmap', 'label' => 'Heatmap (Isı Haritası)'],
                ['value' => 'table', 'label' => 'Tablo'],
            ],
            'filters' => [
                'departments' => $departments,
                'positions' => $positions,
                'cities' => $cities,
                'statuses' => [
                    ['value' => 'active', 'label' => 'Aktif'],
                    ['value' => 'on_leave', 'label' => 'İzinli'],
                    ['value' => 'terminated', 'label' => 'Ayrılmış'],
                ],
                'contract_types' => [
                    ['value' => 'permanent', 'label' => 'Süresiz'],
                    ['value' => 'temporary', 'label' => 'Süreli'],
                    ['value' => 'intern', 'label' => 'Stajyer'],
                    ['value' => 'contract', 'label' => 'Sözleşmeli'],
                ],
                'work_types' => [
                    ['value' => 'full_time', 'label' => 'Tam Zamanlı'],
                    ['value' => 'part_time', 'label' => 'Yarı Zamanlı'],
                    ['value' => 'remote', 'label' => 'Uzaktan'],
                    ['value' => 'hybrid', 'label' => 'Hibrit'],
                ],
                'genders' => [
                    ['value' => 'male', 'label' => 'Erkek'],
                    ['value' => 'female', 'label' => 'Kadın'],
                    ['value' => 'other', 'label' => 'Diğer'],
                ],
            ],
            'color_schemes' => [
                ['value' => 'nivo', 'label' => 'Nivo Varsayılan'],
                ['value' => 'category10', 'label' => 'Kategori 10'],
                ['value' => 'accent', 'label' => 'Accent'],
                ['value' => 'dark2', 'label' => 'Dark 2'],
                ['value' => 'paired', 'label' => 'Paired'],
                ['value' => 'pastel1', 'label' => 'Pastel 1'],
                ['value' => 'set1', 'label' => 'Set 1'],
                ['value' => 'set2', 'label' => 'Set 2'],
            ],
        ]);
    }

    /**
     * Dinamik rapor verisi oluştur
     */
    public function getData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'dimension' => 'required|string',
            'measure' => 'required|string',
            'filters' => 'nullable|array',
            'filters.status' => 'nullable|array',
            'filters.department_id' => 'nullable|array',
            'filters.position' => 'nullable|array',
            'filters.city' => 'nullable|array',
            'filters.contract_type' => 'nullable|array',
            'filters.work_type' => 'nullable|array',
            'filters.gender' => 'nullable|array',
            'filters.date_range' => 'nullable|array',
            'filters.date_range.start' => 'nullable|date',
            'filters.date_range.end' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->error('Geçersiz parametreler', 422, $validator->errors());
        }

        $dimension = $request->input('dimension');
        $measure = $request->input('measure');
        $filters = $request->input('filters', []);

        // Boyut ve metrik kontrolü
        if (! isset($this->dimensions[$dimension])) {
            return $this->error('Geçersiz boyut', 422);
        }

        if (! isset($this->measures[$measure])) {
            return $this->error('Geçersiz metrik', 422);
        }

        // Yetki kontrolü (maaş metrikleri için)
        $measureConfig = $this->measures[$measure];
        if (isset($measureConfig['requires_permission'])
            && ! $this->sensitiveFields->canViewSalary(auth()->user())) {
            return $this->error('Bu metrik için yetkiniz bulunmamaktadır', 403);
        }

        try {
            $data = $this->aggregateData($dimension, $measure, $filters);

            return $this->success([
                'data' => $data,
                'dimension' => [
                    'key' => $dimension,
                    'label' => $this->dimensions[$dimension]['label'],
                ],
                'measure' => [
                    'key' => $measure,
                    'label' => $this->measures[$measure]['label'],
                ],
                'total' => collect($data)->sum('value'),
            ]);
        } catch (\Exception $e) {
            return $this->error('Rapor oluşturulurken bir hata oluştu: '.$e->getMessage(), 500);
        }
    }

    /**
     * Veri aggregation
     */
    private function aggregateData(string $dimension, string $measure, array $filters): array
    {
        $dimensionConfig = $this->dimensions[$dimension];
        $measureConfig = $this->measures[$measure];

        $query = Employee::query()
            ->where('company_id', $this->getCompanyId());

        // Filtreleri uygula
        $query = $this->applyFilters($query, $filters);

        // Metrik özel filtreleri
        if (isset($measureConfig['filter'])) {
            switch ($measureConfig['filter']) {
                case 'recent_hires':
                    $startDate = $filters['date_range']['start'] ?? Carbon::now()->subYear()->format('Y-m-d');
                    $endDate = $filters['date_range']['end'] ?? Carbon::now()->format('Y-m-d');
                    $query->whereBetween('hire_date', [$startDate, $endDate]);
                    break;
                case 'terminated':
                    $query->where('status', 'terminated');
                    if (isset($filters['date_range'])) {
                        $query->whereBetween('termination_date', [
                            $filters['date_range']['start'],
                            $filters['date_range']['end'],
                        ]);
                    }
                    break;
            }
        }

        // Boyuta göre gruplama
        $groupField = $dimensionConfig['field'];
        $dimensionType = $dimensionConfig['type'] ?? 'direct';

        switch ($dimensionType) {
            case 'year':
                $query->selectRaw('YEAR('.$groupField.') as dimension_key');
                $query->groupByRaw('YEAR('.$groupField.')');
                break;
            case 'month':
                $query->selectRaw('DATE_FORMAT('.$groupField.", '%Y-%m') as dimension_key");
                $query->groupByRaw('DATE_FORMAT('.$groupField.", '%Y-%m')");
                break;
            case 'age_group':
                $query->selectRaw("
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 25 THEN '18-24'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 35 THEN '25-34'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 45 THEN '35-44'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 55 THEN '45-54'
                        ELSE '55+'
                    END as dimension_key
                ");
                $query->groupByRaw("
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 25 THEN '18-24'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 35 THEN '25-34'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 45 THEN '35-44'
                        WHEN TIMESTAMPDIFF(YEAR, birth_date, CURDATE()) < 55 THEN '45-54'
                        ELSE '55+'
                    END
                ");
                break;
            case 'seniority_group':
                $query->selectRaw("
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 1 THEN '0-1 Yıl'
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 3 THEN '1-3 Yıl'
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 5 THEN '3-5 Yıl'
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 10 THEN '5-10 Yıl'
                        ELSE '10+ Yıl'
                    END as dimension_key
                ");
                $query->groupByRaw("
                    CASE 
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 1 THEN '0-1 Yıl'
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 3 THEN '1-3 Yıl'
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 5 THEN '3-5 Yıl'
                        WHEN TIMESTAMPDIFF(YEAR, hire_date, CURDATE()) < 10 THEN '5-10 Yıl'
                        ELSE '10+ Yıl'
                    END
                ");
                break;
            default:
                $query->select($groupField.' as dimension_key');
                $query->groupBy($groupField);
                break;
        }

        // Metriğe göre aggregation
        switch ($measure) {
            case 'count':
            case 'hire_count':
            case 'termination_count':
                $query->selectRaw('COUNT(*) as value');
                break;
            case 'avg_age':
                $query->selectRaw('AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as value');
                break;
            case 'avg_seniority':
                $query->selectRaw('AVG(TIMESTAMPDIFF(YEAR, hire_date, CURDATE())) as value');
                break;
            case 'avg_gross_salary':
                $query->selectRaw('AVG(gross_salary) as value');
                break;
            case 'avg_net_salary':
                $query->selectRaw('AVG(net_salary) as value');
                break;
        }

        $results = $query->get();

        // Departman boyutu için isim eşleştirme
        if ($dimension === 'department') {
            $departments = Department::where('company_id', $this->getCompanyId())
                ->pluck('name', 'id');

            return $results->map(function ($item) use ($departments) {
                return [
                    'id' => $item->dimension_key,
                    'label' => $departments[$item->dimension_key] ?? 'Belirtilmemiş',
                    'value' => round($item->value, 2),
                ];
            })->sortByDesc('value')->values()->toArray();
        }

        // Diğer boyutlar için label dönüşümleri
        return $results->map(function ($item) use ($dimension) {
            return [
                'id' => $item->dimension_key ?? 'unknown',
                'label' => $this->getLabel($dimension, $item->dimension_key),
                'value' => round($item->value, 2),
            ];
        })->sortByDesc('value')->values()->toArray();
    }

    /**
     * Filtreleri uygula
     */
    private function applyFilters($query, array $filters)
    {
        if (! empty($filters['status'])) {
            $query->whereIn('status', $filters['status']);
        }

        if (! empty($filters['department_id'])) {
            $query->whereIn('department_id', $filters['department_id']);
        }

        if (! empty($filters['position'])) {
            $query->whereIn('position', $filters['position']);
        }

        if (! empty($filters['city'])) {
            $query->whereIn('city', $filters['city']);
        }

        if (! empty($filters['contract_type'])) {
            $query->whereIn('contract_type', $filters['contract_type']);
        }

        if (! empty($filters['work_type'])) {
            $query->whereIn('work_type', $filters['work_type']);
        }

        if (! empty($filters['gender'])) {
            $query->whereIn('gender', $filters['gender']);
        }

        if (! empty($filters['date_range']['start'])) {
            $query->where('hire_date', '>=', $filters['date_range']['start']);
        }

        if (! empty($filters['date_range']['end'])) {
            $query->where('hire_date', '<=', $filters['date_range']['end']);
        }

        return $query;
    }

    /**
     * Değer için etiket getir
     */
    private function getLabel(string $dimension, $value): string
    {
        if ($value === null) {
            return 'Belirtilmemiş';
        }

        $labels = [
            'gender' => [
                'male' => 'Erkek',
                'female' => 'Kadın',
                'other' => 'Diğer',
            ],
            'marital_status' => [
                'single' => 'Bekar',
                'married' => 'Evli',
                'divorced' => 'Boşanmış',
                'widowed' => 'Dul',
            ],
            'contract_type' => [
                'permanent' => 'Süresiz',
                'temporary' => 'Süreli',
                'intern' => 'Stajyer',
                'contract' => 'Sözleşmeli',
            ],
            'work_type' => [
                'full_time' => 'Tam Zamanlı',
                'part_time' => 'Yarı Zamanlı',
                'remote' => 'Uzaktan',
                'hybrid' => 'Hibrit',
            ],
            'status' => [
                'active' => 'Aktif',
                'on_leave' => 'İzinli',
                'terminated' => 'Ayrılmış',
            ],
            'education_level' => [
                'primary' => 'İlkokul',
                'secondary' => 'Ortaokul',
                'high_school' => 'Lise',
                'associate' => 'Ön Lisans',
                'bachelor' => 'Lisans',
                'master' => 'Yüksek Lisans',
                'doctorate' => 'Doktora',
            ],
        ];

        if (isset($labels[$dimension][$value])) {
            return $labels[$dimension][$value];
        }

        return (string) $value;
    }

    /**
     * Kayıtlı raporları listele
     */
    public function savedReports(Request $request): JsonResponse
    {
        $query = SavedReport::where('company_id', $this->getCompanyId())
            ->accessibleBy(auth()->id());

        if ($request->boolean('favorites_only')) {
            $query->favorites();
        }

        $reports = $query->orderBy('is_favorite', 'desc')
            ->orderBy('sort_order')
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($report) {
                return [
                    'id' => $report->id,
                    'name' => $report->name,
                    'description' => $report->description,
                    'config' => $report->config,
                    'is_favorite' => $report->is_favorite,
                    'is_shared' => $report->is_shared,
                    'is_owner' => $report->user_id === auth()->id(),
                    'created_at' => $report->created_at->format('Y-m-d H:i'),
                    'updated_at' => $report->updated_at->format('Y-m-d H:i'),
                    'user' => [
                        'id' => $report->user->id,
                        'name' => $report->user->name,
                    ],
                ];
            });

        return $this->success($reports);
    }

    /**
     * Yeni rapor kaydet
     */
    public function saveReport(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'config' => 'required|array',
            'config.dimension' => 'required|string',
            'config.measure' => 'required|string',
            'config.chartType' => 'required|string',
            'is_shared' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Geçersiz veriler', 422, $validator->errors());
        }

        $report = SavedReport::create([
            'company_id' => $this->getCompanyId(),
            'user_id' => auth()->id(),
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'config' => $request->input('config'),
            'is_shared' => $request->boolean('is_shared'),
        ]);

        return $this->success([
            'id' => $report->id,
            'name' => $report->name,
            'message' => 'Rapor başarıyla kaydedildi',
        ], 'Rapor kaydedildi', 201);
    }

    /**
     * Rapor güncelle
     */
    public function updateReport(Request $request, int $id): JsonResponse
    {
        $report = SavedReport::where('company_id', $this->getCompanyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'config' => 'sometimes|required|array',
            'is_shared' => 'boolean',
        ]);

        if ($validator->fails()) {
            return $this->error('Geçersiz veriler', 422, $validator->errors());
        }

        $report->update($request->only(['name', 'description', 'config', 'is_shared']));

        return $this->success($report, 'Rapor güncellendi');
    }

    /**
     * Rapor sil
     */
    public function deleteReport(int $id): JsonResponse
    {
        $report = SavedReport::where('company_id', $this->getCompanyId())
            ->where('user_id', auth()->id())
            ->findOrFail($id);

        $report->delete();

        return $this->success(null, 'Rapor silindi');
    }

    /**
     * Favori durumunu değiştir
     */
    public function toggleFavorite(int $id): JsonResponse
    {
        $report = SavedReport::where('company_id', $this->getCompanyId())
            ->accessibleBy(auth()->id())
            ->findOrFail($id);

        $report->is_favorite = ! $report->is_favorite;
        $report->save();

        return $this->success([
            'is_favorite' => $report->is_favorite,
        ], $report->is_favorite ? 'Favorilere eklendi' : 'Favorilerden çıkarıldı');
    }

    /**
     * Raporu Excel olarak dışa aktar
     */
    public function exportExcel(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $validator = Validator::make($request->all(), [
            'dimension' => 'required|string',
            'measure' => 'required|string',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            abort(422, 'Geçersiz parametreler');
        }

        $dimension = $request->input('dimension');
        $measure = $request->input('measure');
        $filters = $request->input('filters', []);

        $data = $this->aggregateData($dimension, $measure, $filters);

        $dimensionLabel = $this->dimensions[$dimension]['label'] ?? $dimension;
        $measureLabel = $this->measures[$measure]['label'] ?? $measure;

        $filename = 'rapor_'.date('Y-m-d_H-i-s').'.csv';

        return response()->streamDownload(function () use ($data, $dimensionLabel, $measureLabel) {
            $file = fopen('php://output', 'w');

            // UTF-8 BOM
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($file, [$dimensionLabel, $measureLabel], ';');

            // Data
            foreach ($data as $row) {
                fputcsv($file, [$row['label'], $row['value']], ';');
            }

            fclose($file);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
