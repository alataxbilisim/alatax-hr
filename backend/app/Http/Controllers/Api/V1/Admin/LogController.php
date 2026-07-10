<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogController extends BaseController
{
    /**
     * Tüm sistem logları (SuperAdmin için)
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::with(['user:id,name,email', 'company:id,name'])
            ->orderBy('created_at', 'desc');

        // Firma filtresi
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
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

        // Başarı durumu
        if ($request->has('is_successful')) {
            $query->where('is_successful', $request->boolean('is_successful'));
        }

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('user_name', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate($request->get('per_page', 50));

        return $this->paginated($logs, 'Loglar listelendi');
    }
}

