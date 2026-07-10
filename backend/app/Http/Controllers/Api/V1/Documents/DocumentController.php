<?php

namespace App\Http\Controllers\Api\V1\Documents;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\DocumentVersion;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends BaseController
{
    /**
     * Doküman listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Document::with(['category', 'uploadedBy']);

        // Kategori filtresi
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Dosya tipi filtresi
        if ($request->filled('file_type')) {
            $fileType = $request->file_type;
            // Genel tipler için mapping
            $mimeTypes = [
                'pdf' => ['application/pdf'],
                'image' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'],
                'document' => ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
                'spreadsheet' => ['application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
                'presentation' => ['application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
                'archive' => ['application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed'],
            ];
            
            if (isset($mimeTypes[$fileType])) {
                $query->whereIn('file_type', $mimeTypes[$fileType]);
            } else {
                $query->where('file_type', 'like', "%{$fileType}%");
            }
        }

        // Tarih aralığı filtresi
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Onay durumu filtresi
        if ($request->filled('approval_status')) {
            $query->where('approval_status', $request->approval_status);
        }

        // Yükleyen kullanıcı filtresi
        if ($request->filled('uploaded_by')) {
            $query->where('uploaded_by', $request->uploaded_by);
        }

        // Arama
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('file_name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Sıralama
        $sortField = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $allowedSorts = ['name', 'file_name', 'file_size', 'created_at', 'updated_at'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $documents = $query->paginate($request->per_page ?? 20);

        $data = $documents->through(function ($doc) {
            return [
                'id' => $doc->id,
                'name' => $doc->name,
                'file_name' => $doc->file_name,
                'file_path' => $doc->file_path ? asset('storage/' . $doc->file_path) : null,
                'file_size' => $doc->file_size,
                'file_type' => $doc->file_type,
                'category' => $doc->category ? [
                    'id' => $doc->category->id,
                    'name' => $doc->category->name,
                ] : null,
                'category_id' => $doc->category_id,
                'description' => $doc->description,
                'version' => $doc->version,
                'current_version' => $doc->current_version ?? $doc->version,
                'validity_date' => $doc->validity_date,
                'approval_status' => $doc->approval_status ?? 'approved',
                'metadata' => $doc->metadata,
                'uploaded_by' => $doc->uploadedBy ? [
                    'id' => $doc->uploadedBy->id,
                    'name' => $doc->uploadedBy->name,
                ] : null,
                'created_at' => $doc->created_at->toDateTimeString(),
                'updated_at' => $doc->updated_at->toDateTimeString(),
            ];
        });

        return $this->success($data);
    }

    /**
     * Doküman yükle
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:document_categories,id',
        ]);

        $file = $request->file('file');
        $path = $file->store('documents/' . $this->getCompanyId(), 'public');

        $document = Document::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'category_id' => $validated['category_id'] ?? null,
            'description' => $validated['description'] ?? null,
            'version' => 1,
            'uploaded_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $document, 'Doküman yüklendi: ' . $document->name);

        return $this->success([
            'id' => $document->id,
            'name' => $document->name,
            'file_path' => asset('storage/' . $document->file_path),
        ], 'Doküman başarıyla yüklendi', 201);
    }

    /**
     * Doküman detayı
     */
    public function show(int $id): JsonResponse
    {
        $document = Document::with(['category', 'uploadedBy'])->find($id);

        if (!$document) {
            return $this->notFound('Doküman bulunamadı');
        }

        // Versiyon geçmişini al
        $versions = [];
        if (class_exists(DocumentVersion::class)) {
            $versions = DocumentVersion::where('document_id', $id)
                ->with('uploader:id,name')
                ->orderBy('version_number', 'desc')
                ->get()
                ->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'version_number' => $v->version_number,
                        'file_name' => $v->file_name,
                        'file_size' => $v->file_size,
                        'change_notes' => $v->change_notes,
                        'uploaded_by' => $v->uploader ? [
                            'id' => $v->uploader->id,
                            'name' => $v->uploader->name,
                        ] : null,
                        'created_at' => $v->created_at->toDateTimeString(),
                    ];
                });
        }

        return $this->success([
            'id' => $document->id,
            'name' => $document->name,
            'file_name' => $document->file_name,
            'file_path' => $document->file_path ? asset('storage/' . $document->file_path) : null,
            'file_size' => $document->file_size,
            'file_type' => $document->file_type,
            'category' => $document->category,
            'category_id' => $document->category_id,
            'description' => $document->description,
            'version' => $document->version,
            'current_version' => $document->current_version ?? $document->version,
            'validity_date' => $document->validity_date,
            'approval_status' => $document->approval_status ?? 'approved',
            'requires_approval' => $document->requires_approval ?? false,
            'metadata' => $document->metadata,
            'uploaded_by' => $document->uploadedBy ? [
                'id' => $document->uploadedBy->id,
                'name' => $document->uploadedBy->name,
            ] : null,
            'versions' => $versions,
            'created_at' => $document->created_at->toDateTimeString(),
            'updated_at' => $document->updated_at->toDateTimeString(),
        ]);
    }

    /**
     * Doküman indir
     */
    public function download(int $id): StreamedResponse|JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return $this->notFound('Doküman bulunamadı');
        }

        if (!$document->file_path || !Storage::disk('public')->exists($document->file_path)) {
            return $this->error('Dosya bulunamadı', 404);
        }

        ActivityLog::log('download', $document, 'Doküman indirildi: ' . $document->name);

        return Storage::disk('public')->download(
            $document->file_path,
            $document->file_name,
            [
                'Content-Type' => $document->file_type,
            ]
        );
    }

    /**
     * Versiyon geçmişi
     */
    public function versions(int $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return $this->notFound('Doküman bulunamadı');
        }

        $versions = [];
        if (class_exists(DocumentVersion::class)) {
            $versions = DocumentVersion::where('document_id', $id)
                ->with('uploader:id,name')
                ->orderBy('version_number', 'desc')
                ->get()
                ->map(function ($v) {
                    return [
                        'id' => $v->id,
                        'version_number' => $v->version_number,
                        'file_name' => $v->file_name,
                        'file_size' => $v->file_size,
                        'file_type' => $v->file_type,
                        'change_notes' => $v->change_notes,
                        'uploaded_by' => $v->uploader ? [
                            'id' => $v->uploader->id,
                            'name' => $v->uploader->name,
                        ] : null,
                        'created_at' => $v->created_at->toDateTimeString(),
                    ];
                });
        }

        return $this->success($versions);
    }

    /**
     * Belirli bir versiyonu indir
     */
    public function downloadVersion(int $id, int $versionId): StreamedResponse|JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return $this->notFound('Doküman bulunamadı');
        }

        if (!class_exists(DocumentVersion::class)) {
            return $this->error('Versiyon sistemi aktif değil', 400);
        }

        $version = DocumentVersion::where('document_id', $id)->where('id', $versionId)->first();

        if (!$version) {
            return $this->notFound('Versiyon bulunamadı');
        }

        if (!$version->file_path || !Storage::disk('public')->exists($version->file_path)) {
            return $this->error('Dosya bulunamadı', 404);
        }

        ActivityLog::log('download', $document, 'Doküman versiyonu indirildi: ' . $document->name . ' v' . $version->version_number);

        return Storage::disk('public')->download(
            $version->file_path,
            $version->file_name,
            [
                'Content-Type' => $version->file_type,
            ]
        );
    }

    /**
     * İstatistikler
     */
    public function stats(): JsonResponse
    {
        $companyId = $this->getCompanyId();

        $totalDocuments = Document::where('company_id', $companyId)->count();
        $totalSize = Document::where('company_id', $companyId)->sum('file_size');
        $byCategory = Document::where('company_id', $companyId)
            ->selectRaw('category_id, COUNT(*) as count')
            ->groupBy('category_id')
            ->with('category:id,name')
            ->get()
            ->map(function ($item) {
                return [
                    'category_id' => $item->category_id,
                    'category_name' => $item->category?->name ?? 'Kategorisiz',
                    'count' => $item->count,
                ];
            });

        $byType = Document::where('company_id', $companyId)
            ->selectRaw('file_type, COUNT(*) as count, SUM(file_size) as total_size')
            ->groupBy('file_type')
            ->get();

        $recentUploads = Document::where('company_id', $companyId)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        return $this->success([
            'total_documents' => $totalDocuments,
            'total_size' => $totalSize,
            'total_size_formatted' => $this->formatFileSize($totalSize),
            'by_category' => $byCategory,
            'by_type' => $byType,
            'recent_uploads_30d' => $recentUploads,
        ]);
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

    /**
     * Doküman güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return $this->notFound('Doküman bulunamadı');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:document_categories,id',
        ]);

        $oldValues = $document->getOriginal();
        $document->update($validated);

        ActivityLog::log('update', $document, 'Doküman güncellendi: ' . $document->name, $oldValues, $document->fresh()->toArray());

        return $this->success($document, 'Doküman güncellendi');
    }

    /**
     * Doküman sil
     */
    public function destroy(int $id): JsonResponse
    {
        $document = Document::find($id);

        if (!$document) {
            return $this->notFound('Doküman bulunamadı');
        }

        // Dosyayı sil
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }

        ActivityLog::log('delete', $document, 'Doküman silindi: ' . $document->name);
        
        $document->delete();

        return $this->success(null, 'Doküman silindi');
    }
}
