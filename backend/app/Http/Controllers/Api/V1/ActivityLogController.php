<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Services\DataScopeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ActivityLogController extends BaseController
{
    public function __construct(
        protected DataScopeService $dataScope,
    ) {}

    /**
     * Activity log listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->baseScopedQuery($request)
            ->with('user:id,name,email')
            ->orderBy('created_at', 'desc');

        $logs = $query->paginate($request->get('per_page', 50));

        return $this->paginated($logs, 'Loglar listelendi');
    }

    /**
     * Index + export ortak kapsamlı sorgu (tenant + DataScope + filtreler).
     *
     * @return Builder<ActivityLog>
     */
    protected function baseScopedQuery(Request $request): Builder
    {
        $query = ActivityLog::query();

        if (! $this->isSuperAdmin()) {
            $query->where('company_id', $this->getCompanyId());
        }

        $this->dataScope->scopeForUser($query, $request->user(), 'user_id');

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
        if ($request->has('model_type')) {
            $query->where('model_type', 'like', '%'.$request->model_type.'%');
        }
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Activity log detayı
     */
    public function show(int $id): JsonResponse
    {
        $query = ActivityLog::with('user:id,name,email');

        if (! $this->isSuperAdmin()) {
            $query->where('company_id', $this->getCompanyId());
        }

        $this->dataScope->scopeForUser($query, request()->user(), 'user_id');

        $log = $query->find($id);

        if (! $log) {
            return $this->notFound('Log kaydı bulunamadı');
        }

        return $this->success($log, 'Log detayı getirildi');
    }

    /**
     * Log export (CSV) — index ile aynı baseScopedQuery; chunk/stream.
     */
    public function export(Request $request): StreamedResponse
    {
        $filename = 'activity_logs_'.date('Y-m-d_His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        return response()->stream(function () use ($request) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Tarih', 'Kullanıcı', 'E-posta', 'İşlem', 'Model', 'Model ID', 'Açıklama', 'IP Adresi', 'Durum']);

            $this->baseScopedQuery($request)
                ->with('user:id,name,email')
                ->orderBy('id')
                ->chunkById(500, function ($logs) use ($file) {
                    foreach ($logs as $log) {
                        fputcsv($file, [
                            $log->created_at?->format('Y-m-d H:i:s') ?? '',
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
                });

            fclose($file);

            // Stream bittikten sonra — export satırlarına karışmasın
            ActivityLog::log('export', null, 'Log export edildi');
        }, 200, $headers);
    }
}
