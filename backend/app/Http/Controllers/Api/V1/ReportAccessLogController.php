<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ReportAccessLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * D1e — Rapor erişim logları (append-only, yönetim audit altında).
 */
class ReportAccessLogController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'contains_sensitive' => 'nullable|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $q = ReportAccessLog::query()
            ->where('company_id', $this->getCompanyId())
            ->with(['user:id,name,email'])
            ->orderByDesc('created_at');

        if (! empty($validated['user_id'])) {
            $q->where('user_id', (int) $validated['user_id']);
        }
        if (! empty($validated['from'])) {
            $q->where('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $q->where('created_at', '<=', $validated['to'].' 23:59:59');
        }
        if (array_key_exists('contains_sensitive', $validated) && $validated['contains_sensitive'] !== null) {
            $q->where('contains_sensitive', (bool) $validated['contains_sensitive']);
        }

        return $this->paginated($q->paginate($validated['per_page'] ?? 25), 'Rapor erişimleri');
    }
}
