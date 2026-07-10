<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends BaseController
{
    /**
     * Activity log listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        // SuperAdmin değilse sadece kendi firmasının loglarını görsün
        if (! $this->isSuperAdmin()) {
            $query->where('company_id', $this->getCompanyId());
        }

        // Tarih filtresi
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Kullanıcı filtresi
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Action filtresi
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        // Model filtresi
        if ($request->has('model_type')) {
            $query->where('model_type', 'like', '%'.$request->model_type.'%');
        }

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate($request->get('per_page', 50));

        return $this->paginated($logs, 'Loglar listelendi');
    }

    /**
     * Activity log detayı
     */
    public function show(int $id): JsonResponse
    {
        $query = ActivityLog::with('user:id,name,email');

        // SuperAdmin değilse sadece kendi firmasının loglarını görsün
        if (! $this->isSuperAdmin()) {
            $query->where('company_id', $this->getCompanyId());
        }

        $log = $query->find($id);

        if (! $log) {
            return $this->notFound('Log kaydı bulunamadı');
        }

        return $this->success($log, 'Log detayı getirildi');
    }

    /**
     * Log export (CSV)
     */
    public function export(Request $request): StreamedResponse
    {
        $query = ActivityLog::with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        // SuperAdmin değilse sadece kendi firmasının loglarını görsün
        if (! $this->isSuperAdmin()) {
            $query->where('company_id', $this->getCompanyId());
        }

        // Filtreler
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('action')) {
            $query->where('action', $request->action);
        }

        $logs = $query->get();

        // CSV oluştur
        $filename = 'activity_logs_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');

            // BOM for Excel UTF-8 support
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));

            // Header
            fputcsv($file, ['Tarih', 'Kullanıcı', 'E-posta', 'İşlem', 'Model', 'Model ID', 'Açıklama', 'IP Adresi', 'Durum']);

            // Data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->created_at->format('Y-m-d H:i:s'),
                    $log->user?->name ?? 'Sistem',
                    $log->user?->email ?? '',
                    $log->action,
                    $log->model_type,
                    $log->model_id,
                    $log->description ?? '',
                    $log->ip_address ?? '',
                    $log->is_successful ? 'Başarılı' : 'Başarısız',
                ]);
            }

            fclose($file);
        };

        ActivityLog::log('export', null, 'Log export edildi');

        return response()->stream($callback, 200, $headers);
    }
}
