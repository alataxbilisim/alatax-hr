<?php

namespace App\Http\Controllers\Api\V1\Kvkk;

use App\Http\Controllers\Controller;
use App\Jobs\ExecuteDestructionApprovalJob;
use App\Models\DestructionCandidate;
use App\Models\DestructionLog;
use App\Models\RetentionPolicy;
use App\Services\Kvkk\Retention\DestructionEngine;
use App\Services\Settings\Settings;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * İmha kuyruğu — sistem yalnız listeler; karar ve uygulama insana aittir.
 */
class DestructionController extends Controller
{
    use ApiResponse;

    public function __construct(private DestructionEngine $engine) {}

    public function summary(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $pending = DestructionCandidate::query()->where('company_id', $companyId)->where('status', 'pending')->count();
        $deferred = DestructionCandidate::query()->where('company_id', $companyId)->where('status', 'deferred')->count();
        $policyCount = RetentionPolicy::query()->where('company_id', $companyId)->where('active', true)->count();
        $months = (int) Settings::get('kvkk.destruction.review_period_months', ['company_id' => $companyId]);
        $last = DestructionLog::query()
            ->where('company_id', $companyId)
            ->where('dry_run', false)
            ->orderByDesc('id')
            ->value('created_at');

        return $this->success([
            'pending_count' => $pending,
            'deferred_count' => $deferred,
            'active_policy_count' => $policyCount,
            'no_policy_defined' => $policyCount === 0,
            'review_period_months' => $months > 0 ? $months : 6,
            'last_destruction_at' => $last,
            'backup_disclaimer' => true,
        ], 'İmha özeti');
    }

    public function candidates(Request $request): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $q = DestructionCandidate::query()
            ->where('company_id', $companyId)
            ->with('policy:id,name,strategy,retention_months')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $q->where('status', $request->string('status'));
        }

        return $this->paginated($q->paginate(min(100, max(1, (int) $request->input('per_page', 25)))), 'İmha adayları');
    }

    public function scan(Request $request): JsonResponse
    {
        $result = $this->engine->scan((int) $request->user()->company_id);

        return $this->success($result, 'Tarama tamamlandı (veriye dokunulmadı)');
    }

    public function decide(Request $request, int $id): JsonResponse
    {
        $candidate = DestructionCandidate::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $data = $request->validate([
            'decision' => ['required', 'in:destroy,defer,exclude'],
            'reason' => ['required', 'string', 'min:3'],
            'defer_until' => ['nullable', 'date'],
        ]);

        $decision = $this->engine->decide(
            $candidate,
            $request->user(),
            $data['decision'],
            $data['reason'],
            $data['defer_until'] ?? null,
        );

        return $this->success($decision, 'Karar kaydedildi');
    }

    public function dryRun(Request $request, int $id): JsonResponse
    {
        $candidate = DestructionCandidate::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $preview = $this->engine->dryRun($candidate);

        return $this->success($preview, 'Dry-run tamamlandı (değişiklik yok)');
    }

    public function approve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'candidate_ids' => ['required', 'array', 'min:1'],
            'candidate_ids.*' => ['integer'],
            'dry_run_confirmed' => ['required', 'boolean', 'accepted'],
            'note' => ['nullable', 'string'],
        ]);

        $approval = $this->engine->approve(
            (int) $request->user()->company_id,
            $request->user(),
            $data['candidate_ids'],
            (bool) $data['dry_run_confirmed'],
            $data['note'] ?? null,
        );

        ExecuteDestructionApprovalJob::dispatch($approval->id);

        return $this->success($approval, 'Onaylandı; imha kuyruğa alındı');
    }

    public function logs(Request $request): JsonResponse
    {
        $rows = DestructionLog::query()
            ->where('company_id', $request->user()->company_id)
            ->orderByDesc('id')
            ->paginate(min(100, max(1, (int) $request->input('per_page', 25))));

        return $this->paginated($rows, 'İmha kayıtları');
    }

    public function logCertificate(Request $request, int $id): Response
    {
        $log = DestructionLog::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);

        $html = '<!DOCTYPE html><html><head><meta charset="utf-8"><title>İmha Tutanağı</title></head><body>'
            .'<h1>Kişisel Veri İmha Tutanağı</h1>'
            .'<p>ID: '.$log->id.'</p>'
            .'<p>Tarih: '.e((string) $log->created_at).'</p>'
            .'<p>Subject: '.e($log->subject_type).' #'.$log->subject_id.'</p>'
            .'<p>Strateji: '.e((string) $log->strategy).'</p>'
            .'<p>Collector: '.e((string) $log->collector_key).'</p>'
            .'<p>Satır: '.$log->rows_affected.'</p>'
            .'<p>Hash: '.e((string) $log->content_hash).'</p>'
            .'<p><em>Yedeklerdeki kopyalar yedek saklama süresi dolduğunda ortadan kalkar; yedek politikanızı buna göre tanımlayın.</em></p>'
            .'</body></html>';

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="imha-tutanagi-'.$log->id.'.html"',
        ]);
    }
}
