<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ReportController extends BaseController
{
    /**
     * Rapor metadata'sı (boyutlar ve metrikler)
     */
    public function metadata(): JsonResponse
    {
        $dimensions = [
            ['value' => 'category', 'label' => 'Kategori'],
            ['value' => 'file_type', 'label' => 'Dosya Tipi'],
            ['value' => 'uploaded_by', 'label' => 'Yükleyen'],
            ['value' => 'approval_status', 'label' => 'Onay Durumu'],
            ['value' => 'month', 'label' => 'Ay'],
            ['value' => 'year', 'label' => 'Yıl'],
        ];

        $measures = [
            ['value' => 'count', 'label' => 'Belge Sayısı'],
            ['value' => 'total_size', 'label' => 'Toplam Boyut'],
            ['value' => 'avg_size', 'label' => 'Ortalama Boyut'],
        ];

        // Kategori listesi
        $categories = DocumentCategory::where('company_id', $this->getCompanyId())
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        // Yükleyenler listesi
        $uploaders = Document::where('company_id', $this->getCompanyId())
            ->whereNotNull('uploaded_by')
            ->select('uploaded_by')
            ->distinct()
            ->with('uploadedBy:id,name')
            ->get()
            ->map(function ($doc) {
                return $doc->uploadedBy ? [
                    'id' => $doc->uploadedBy->id,
                    'name' => $doc->uploadedBy->name,
                ] : null;
            })
            ->filter()
            ->values();

        // Dosya tipleri
        $fileTypes = Document::where('company_id', $this->getCompanyId())
            ->select('file_type')
            ->distinct()
            ->pluck('file_type')
            ->filter()
            ->map(function ($type) {
                return [
                    'value' => $type,
                    'label' => $this->getFileTypeLabel($type),
                ];
            })
            ->values();

        return $this->success([
            'dimensions' => $dimensions,
            'measures' => $measures,
            'filters' => [
                'categories' => $categories,
                'uploaders' => $uploaders,
                'file_types' => $fileTypes,
                'approval_statuses' => [
                    ['value' => 'draft', 'label' => 'Taslak'],
                    ['value' => 'pending', 'label' => 'Onay Bekliyor'],
                    ['value' => 'approved', 'label' => 'Onaylandı'],
                    ['value' => 'rejected', 'label' => 'Reddedildi'],
                ],
            ],
        ]);
    }

    /**
     * Widget verisi al
     */
    public function getWidgetData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dimension' => 'required|string',
            'measure' => 'required|string',
            'filters' => 'nullable|array',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $dimension = $validated['dimension'];
        $measure = $validated['measure'];
        $filters = $validated['filters'] ?? [];
        $limit = $validated['limit'] ?? 10;

        $companyId = $this->getCompanyId();
        $query = Document::where('company_id', $companyId);

        // Filtreleri uygula
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['file_type'])) {
            $query->where('file_type', $filters['file_type']);
        }
        if (!empty($filters['uploaded_by'])) {
            $query->where('uploaded_by', $filters['uploaded_by']);
        }
        if (!empty($filters['approval_status'])) {
            $query->where('approval_status', $filters['approval_status']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Boyut ve metrik'e göre veri çek
        $data = $this->buildQueryByDimension($query, $dimension, $measure, $limit);

        return $this->success($data);
    }

    /**
     * Boyuta göre sorgu oluştur
     */
    private function buildQueryByDimension($query, string $dimension, string $measure, int $limit): array
    {
        $measureSelect = match ($measure) {
            'count' => DB::raw('COUNT(*) as value'),
            'total_size' => DB::raw('SUM(file_size) as value'),
            'avg_size' => DB::raw('AVG(file_size) as value'),
            default => DB::raw('COUNT(*) as value'),
        };

        switch ($dimension) {
            case 'category':
                $results = (clone $query)
                    ->select('category_id', $measureSelect)
                    ->groupBy('category_id')
                    ->orderByDesc('value')
                    ->limit($limit)
                    ->get();

                $categories = DocumentCategory::whereIn('id', $results->pluck('category_id'))
                    ->pluck('name', 'id');

                return $results->map(function ($item) use ($categories) {
                    return [
                        'name' => $categories[$item->category_id] ?? 'Kategorisiz',
                        'value' => (float) $item->value,
                    ];
                })->toArray();

            case 'file_type':
                return (clone $query)
                    ->select('file_type', $measureSelect)
                    ->groupBy('file_type')
                    ->orderByDesc('value')
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'name' => $this->getFileTypeLabel($item->file_type),
                            'value' => (float) $item->value,
                        ];
                    })->toArray();

            case 'uploaded_by':
                $results = (clone $query)
                    ->select('uploaded_by', $measureSelect)
                    ->groupBy('uploaded_by')
                    ->orderByDesc('value')
                    ->limit($limit)
                    ->get();

                $users = User::whereIn('id', $results->pluck('uploaded_by'))
                    ->pluck('name', 'id');

                return $results->map(function ($item) use ($users) {
                    return [
                        'name' => $users[$item->uploaded_by] ?? 'Bilinmiyor',
                        'value' => (float) $item->value,
                    ];
                })->toArray();

            case 'approval_status':
                $statusLabels = [
                    'draft' => 'Taslak',
                    'pending' => 'Onay Bekliyor',
                    'approved' => 'Onaylandı',
                    'rejected' => 'Reddedildi',
                ];

                return (clone $query)
                    ->select('approval_status', $measureSelect)
                    ->groupBy('approval_status')
                    ->orderByDesc('value')
                    ->get()
                    ->map(function ($item) use ($statusLabels) {
                        return [
                            'name' => $statusLabels[$item->approval_status] ?? $item->approval_status ?? 'Onaylandı',
                            'value' => (float) $item->value,
                        ];
                    })->toArray();

            case 'month':
                return (clone $query)
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        $measureSelect
                    )
                    ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                    ->orderBy(DB::raw('YEAR(created_at)'))
                    ->orderBy(DB::raw('MONTH(created_at)'))
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        $months = ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
                        return [
                            'name' => $months[$item->month] . ' ' . $item->year,
                            'value' => (float) $item->value,
                        ];
                    })->toArray();

            case 'year':
                return (clone $query)
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        $measureSelect
                    )
                    ->groupBy(DB::raw('YEAR(created_at)'))
                    ->orderBy('year')
                    ->limit($limit)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'name' => (string) $item->year,
                            'value' => (float) $item->value,
                        ];
                    })->toArray();

            default:
                return [];
        }
    }

    /**
     * KPI verileri
     */
    public function getKpiData(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kpi_type' => 'required|string',
            'filters' => 'nullable|array',
        ]);

        $kpiType = $validated['kpi_type'];
        $filters = $validated['filters'] ?? [];
        $companyId = $this->getCompanyId();

        $query = Document::where('company_id', $companyId);

        // Filtreleri uygula
        if (!empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }
        if (!empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $result = match ($kpiType) {
            'total_documents' => [
                'value' => $query->count(),
                'label' => 'Toplam Belge',
            ],
            'total_size' => [
                'value' => $query->sum('file_size'),
                'label' => 'Toplam Boyut',
                'formatted' => $this->formatFileSize($query->sum('file_size')),
            ],
            'avg_size' => [
                'value' => $query->avg('file_size') ?? 0,
                'label' => 'Ortalama Boyut',
                'formatted' => $this->formatFileSize((int) ($query->avg('file_size') ?? 0)),
            ],
            'categories_count' => [
                'value' => DocumentCategory::where('company_id', $companyId)->count(),
                'label' => 'Kategori Sayısı',
            ],
            'this_month' => [
                'value' => (clone $query)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
                'label' => 'Bu Ay Yüklenen',
            ],
            'pending_approval' => [
                'value' => (clone $query)->where('approval_status', 'pending')->count(),
                'label' => 'Onay Bekleyen',
            ],
            default => [
                'value' => 0,
                'label' => 'Bilinmiyor',
            ],
        };

        return $this->success($result);
    }

    /**
     * Özet rapor
     */
    public function summary(): JsonResponse
    {
        $companyId = $this->getCompanyId();

        $totalDocuments = Document::where('company_id', $companyId)->count();
        $totalSize = Document::where('company_id', $companyId)->sum('file_size');
        $categoriesCount = DocumentCategory::where('company_id', $companyId)->count();
        $thisMonth = Document::where('company_id', $companyId)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $lastMonth = Document::where('company_id', $companyId)
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();

        // Son 6 ay trend
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $count = Document::where('company_id', $companyId)
                ->whereMonth('created_at', $date->month)
                ->whereYear('created_at', $date->year)
                ->count();
            $months = ['', 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
            $trend[] = [
                'name' => $months[$date->month] . ' ' . $date->year,
                'value' => $count,
            ];
        }

        // Kategori dağılımı
        $byCategory = Document::where('company_id', $companyId)
            ->select('category_id', DB::raw('COUNT(*) as count'))
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $item->category?->name ?? 'Kategorisiz',
                    'value' => $item->count,
                ];
            });

        // Dosya tipi dağılımı
        $byFileType = Document::where('company_id', $companyId)
            ->select('file_type', DB::raw('COUNT(*) as count'))
            ->groupBy('file_type')
            ->get()
            ->map(function ($item) {
                return [
                    'name' => $this->getFileTypeLabel($item->file_type),
                    'value' => $item->count,
                ];
            });

        return $this->success([
            'total_documents' => $totalDocuments,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatFileSize($totalSize),
            'categories_count' => $categoriesCount,
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'month_change' => $lastMonth > 0 ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1) : 0,
            'trend' => $trend,
            'by_category' => $byCategory,
            'by_file_type' => $byFileType,
        ]);
    }

    /**
     * Dosya tipi etiketi
     */
    private function getFileTypeLabel(?string $mimeType): string
    {
        if (!$mimeType) return 'Bilinmiyor';

        $labels = [
            'application/pdf' => 'PDF',
            'image/jpeg' => 'JPEG Resim',
            'image/png' => 'PNG Resim',
            'image/gif' => 'GIF Resim',
            'image/webp' => 'WebP Resim',
            'application/msword' => 'Word Belgesi',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Belgesi',
            'application/vnd.ms-excel' => 'Excel Tablosu',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Excel Tablosu',
            'application/vnd.ms-powerpoint' => 'PowerPoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'PowerPoint',
            'application/zip' => 'ZIP Arşivi',
            'application/x-rar-compressed' => 'RAR Arşivi',
            'text/plain' => 'Metin Dosyası',
            'text/csv' => 'CSV Dosyası',
        ];

        return $labels[$mimeType] ?? ucfirst(explode('/', $mimeType)[1] ?? 'Dosya');
    }

    /**
     * Dosya boyutunu formatla
     */
    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / (1024 * 1024), 1) . ' MB';
        return round($bytes / (1024 * 1024 * 1024), 2) . ' GB';
    }
}

