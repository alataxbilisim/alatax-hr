<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDashboard;
use App\Services\EmployeeSensitiveFieldService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeeDashboardController extends Controller
{
    public function __construct(
        protected EmployeeSensitiveFieldService $sensitiveFields,
    ) {}

    /**
     * Dashboard listesi (kullanıcının kendi + paylaşılanlar)
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $companyId = $user->company_id;

        $query = EmployeeDashboard::where('company_id', $companyId)
            ->accessibleBy($user->id)
            ->with('user:id,name');

        if ($request->boolean('favorites_only')) {
            $query->favorites();
        }

        $dashboards = $query->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $dashboards,
        ]);
    }

    /**
     * Tek dashboard detayı
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $dashboard = EmployeeDashboard::where('company_id', $user->company_id)
            ->accessibleBy($user->id)
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $dashboard,
        ]);
    }

    /**
     * Yeni dashboard oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|string',
            'widgets.*.type' => 'required|string|in:chart,kpi,table,treemap,text',
            'widgets.*.title' => 'nullable|string|max:255',
            'widgets.*.notes' => 'nullable|string|max:1000',
            'widgets.*.labels' => 'nullable|array',
            'widgets.*.config' => 'required|array',
            'widgets.*.layout' => 'required|array',
            'layout_config' => 'nullable|array',
            'is_shared' => 'boolean',
        ]);

        $user = Auth::user();

        $dashboard = EmployeeDashboard::create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'widgets' => $validated['widgets'],
            'layout_config' => $validated['layout_config'] ?? null,
            'is_shared' => $validated['is_shared'] ?? false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard oluşturuldu',
            'data' => $dashboard,
        ], 201);
    }

    /**
     * Dashboard güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        $dashboard = EmployeeDashboard::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:1000',
            'widgets' => 'sometimes|array',
            'layout_config' => 'nullable|array',
            'is_shared' => 'boolean',
        ]);

        $dashboard->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Dashboard güncellendi',
            'data' => $dashboard->fresh(),
        ]);
    }

    /**
     * Dashboard sil
     */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();

        $dashboard = EmployeeDashboard::where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        $dashboard->delete();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard silindi',
        ]);
    }

    /**
     * Favori toggle
     */
    public function toggleFavorite(int $id): JsonResponse
    {
        $user = Auth::user();

        $dashboard = EmployeeDashboard::where('company_id', $user->company_id)
            ->accessibleBy($user->id)
            ->findOrFail($id);

        $dashboard->update([
            'is_favorite' => ! $dashboard->is_favorite,
        ]);

        return response()->json([
            'success' => true,
            'message' => $dashboard->is_favorite ? 'Favorilere eklendi' : 'Favorilerden çıkarıldı',
            'data' => $dashboard->fresh(),
        ]);
    }

    /**
     * Widget verisi getir (tek widget için)
     */
    public function getWidgetData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:chart,kpi,table,treemap',
            'config' => 'required|array',
            'config.dimension' => 'required_unless:type,kpi|string',
            'config.measure' => 'required|string',
            'config.filters' => 'nullable|array',
        ]);

        $user = Auth::user();
        $type = $validated['type'];
        $config = $validated['config'];

        if ($this->sensitiveFields->isSalaryMeasure($config['measure'] ?? '')
            && ! $this->sensitiveFields->canViewSalary($user)) {
            return response()->json([
                'success' => false,
                'message' => 'Bu metrik için yetkiniz bulunmamaktadır',
            ], 403);
        }

        $query = Employee::where('company_id', $user->company_id);

        // Filtreleri uygula
        if (! empty($config['filters'])) {
            foreach ($config['filters'] as $field => $value) {
                if (! empty($value)) {
                    if (is_array($value)) {
                        $query->whereIn($field, $value);
                    } else {
                        $query->where($field, $value);
                    }
                }
            }
        }

        // KPI widget için
        if ($type === 'kpi') {
            $data = $this->getKPIData($query, $config['measure']);

            return response()->json(['success' => true, 'data' => $data]);
        }

        // Chart/Table/Treemap için grup verisi
        $dimension = $config['dimension'];
        $measure = $config['measure'];

        $data = $this->getAggregatedData($query, $dimension, $measure);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Dashboard'u Excel olarak export et
     */
    public function exportExcel(Request $request, int $id): StreamedResponse
    {
        $user = Auth::user();

        $dashboard = EmployeeDashboard::where('company_id', $user->company_id)
            ->accessibleBy($user->id)
            ->findOrFail($id);

        $spreadsheet = new Spreadsheet;
        $sheetIndex = 0;

        foreach ($dashboard->widgets as $widget) {
            if ($widget['type'] === 'text') {
                continue;
            } // Text widget'ları atla

            // Her widget için ayrı sheet
            if ($sheetIndex > 0) {
                $spreadsheet->createSheet();
            }
            $sheet = $spreadsheet->setActiveSheetIndex($sheetIndex);
            $sheet->setTitle(substr($widget['title'] ?: 'Widget '.($sheetIndex + 1), 0, 31));

            // Widget verisini al
            $config = $widget['config'];

            if ($this->sensitiveFields->isSalaryMeasure($config['measure'] ?? '')
                && ! $this->sensitiveFields->canViewSalary($user)) {
                continue;
            }

            $query = Employee::where('company_id', $user->company_id);

            if ($widget['type'] === 'kpi') {
                $data = $this->getKPIData($query, $config['measure']);
                $sheet->setCellValue('A1', 'Metrik');
                $sheet->setCellValue('B1', 'Değer');
                $row = 2;
                foreach ($data as $key => $value) {
                    $sheet->setCellValue('A'.$row, $key);
                    $sheet->setCellValue('B'.$row, $value);
                    $row++;
                }
            } else {
                $data = $this->getAggregatedData($query, $config['dimension'], $config['measure']);
                $sheet->setCellValue('A1', 'Kategori');
                $sheet->setCellValue('B1', 'Değer');
                $row = 2;
                foreach ($data as $item) {
                    $sheet->setCellValue('A'.$row, $item['name']);
                    $sheet->setCellValue('B'.$row, $item['value']);
                    $row++;
                }
            }

            $sheetIndex++;
        }

        // İlk sheet'i aktif yap
        if ($sheetIndex > 0) {
            $spreadsheet->setActiveSheetIndex(0);
        }

        $filename = 'dashboard_'.$dashboard->id.'_'.date('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * KPI verisi hesapla
     */
    private function getKPIData($query, string $measure): array
    {
        $measureField = $this->getMeasureField($measure);

        if ($measureField === 'count') {
            return [
                'total' => $query->count(),
                'label' => 'Toplam Personel',
            ];
        }

        $stats = $query->selectRaw("
            COUNT(*) as total,
            AVG($measureField) as average,
            MIN($measureField) as min_value,
            MAX($measureField) as max_value,
            SUM($measureField) as sum_value
        ")->first();

        return [
            'total' => $stats->total ?? 0,
            'average' => round($stats->average ?? 0, 2),
            'min' => $stats->min_value ?? 0,
            'max' => $stats->max_value ?? 0,
            'sum' => $stats->sum_value ?? 0,
        ];
    }

    /**
     * Gruplu veri al
     */
    private function getAggregatedData($query, string $dimension, string $measure): array
    {
        $dimensionField = $this->getDimensionField($dimension);
        $measureField = $this->getMeasureField($measure);

        // Hesaplanan boyutlar için özel işlem
        $dimensionSelect = match ($dimensionField) {
            'hire_year' => DB::raw('YEAR(hire_date) as dimension'),
            'birth_year' => DB::raw('YEAR(birth_date) as dimension'),
            default => $dimensionField.' as dimension',
        };

        $dimensionGroup = match ($dimensionField) {
            'hire_year' => DB::raw('YEAR(hire_date)'),
            'birth_year' => DB::raw('YEAR(birth_date)'),
            default => $dimensionField,
        };

        // Hesaplanan metrikler için özel işlem
        $selectRaw = match ($measureField) {
            'count' => 'COUNT(*) as value',
            'age' => 'AVG(TIMESTAMPDIFF(YEAR, birth_date, CURDATE())) as value',
            'tenure' => 'AVG(TIMESTAMPDIFF(YEAR, hire_date, CURDATE())) as value',
            default => "AVG($measureField) as value",
        };

        $results = $query->clone()
            ->selectRaw(is_string($dimensionSelect) ? $dimensionSelect : $dimensionSelect->getValue(DB::connection()->getQueryGrammar()))
            ->selectRaw($selectRaw)
            ->groupBy($dimensionGroup)
            ->orderByDesc('value')
            ->limit(20)
            ->get();

        // department_id için department adlarını çek
        if ($dimensionField === 'department_id') {
            $departmentIds = $results->pluck('dimension')->filter()->toArray();
            $departments = \App\Models\Department::whereIn('id', $departmentIds)->pluck('name', 'id');

            return $results->map(function ($item) use ($departments) {
                return [
                    'name' => $departments[$item->dimension] ?? 'Belirtilmemiş',
                    'value' => round($item->value, 2),
                ];
            })->toArray();
        }

        return $results->map(function ($item) {
            return [
                'name' => $item->dimension ?? 'Belirtilmemiş',
                'value' => round($item->value, 2),
            ];
        })->toArray();
    }

    /**
     * Boyut alanını al
     */
    private function getDimensionField(string $dimension): string
    {
        $map = [
            'department' => 'department_id',
            'position' => 'position',
            'location' => 'city',
            'city' => 'city',
            'employment_type' => 'contract_type',
            'contract_type' => 'contract_type',
            'work_type' => 'work_type',
            'gender' => 'gender',
            'education_level' => 'education_level',
            'marital_status' => 'marital_status',
            'status' => 'status',
            'hire_year' => 'hire_year',
            'birth_year' => 'birth_year',
        ];

        return $map[$dimension] ?? 'department_id';
    }

    /**
     * Metrik alanını al
     */
    private function getMeasureField(string $measure): string
    {
        $map = [
            'count' => 'count',
            'salary' => 'gross_salary',
            'gross_salary' => 'gross_salary',
            'net_salary' => 'net_salary',
            'age' => 'age',
            'tenure' => 'tenure',
        ];

        return $map[$measure] ?? 'count';
    }
}
