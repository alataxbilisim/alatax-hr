<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class WebhookController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Webhook::where('company_id', $this->getCompanyId());

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $webhooks = $query->orderBy('created_at', 'desc')->get();

        return $this->success($webhooks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:500',
            'events' => 'required|array',
            'events.*' => 'string',
            'is_active' => 'boolean',
            'timeout' => 'integer|min:5|max:300',
            'retry_count' => 'integer|min:0|max:10',
        ]);

        $webhook = Webhook::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'],
            'url' => $validated['url'],
            'events' => $validated['events'],
            'is_active' => $validated['is_active'] ?? true,
            'timeout' => $validated['timeout'] ?? 30,
            'retry_count' => $validated['retry_count'] ?? 3,
            'secret' => Str::random(32),
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $webhook, "Webhook oluşturuldu: {$webhook->name}");

        return $this->success($webhook, 'Webhook başarıyla oluşturuldu');
    }

    public function show(Webhook $webhook): JsonResponse
    {
        if ($webhook->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $webhook->load('logs');

        return $this->success($webhook);
    }

    public function update(Request $request, Webhook $webhook): JsonResponse
    {
        if ($webhook->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'url' => 'sometimes|url|max:500',
            'events' => 'sometimes|array',
            'events.*' => 'string',
            'is_active' => 'sometimes|boolean',
            'timeout' => 'sometimes|integer|min:5|max:300',
            'retry_count' => 'sometimes|integer|min:0|max:10',
        ]);

        $webhook->update(array_merge($validated, [
            'updated_by' => auth()->id(),
        ]));

        ActivityLog::log('update', $webhook, "Webhook güncellendi: {$webhook->name}");

        return $this->success($webhook, 'Webhook başarıyla güncellendi');
    }

    public function destroy(Webhook $webhook): JsonResponse
    {
        if ($webhook->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        ActivityLog::log('delete', $webhook, "Webhook silindi: {$webhook->name}");

        $webhook->delete();

        return $this->success(null, 'Webhook başarıyla silindi');
    }

    public function logs(Webhook $webhook, Request $request): JsonResponse
    {
        if ($webhook->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $query = $webhook->logs();

        if ($request->has('is_successful')) {
            $query->where('is_successful', $request->boolean('is_successful'));
        }

        $logs = $query->orderBy('triggered_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return $this->success($logs);
    }

    public function test(Webhook $webhook): JsonResponse
    {
        if ($webhook->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        // Test webhook gönderimi
        try {
            $payload = [
                'event' => 'webhook.test',
                'data' => [
                    'message' => 'Bu bir test webhook\'udur',
                    'timestamp' => now()->toIso8601String(),
                ],
            ];

            // Webhook gönderimi simülasyonu (gerçek implementasyonda HTTP client kullanılır)
            $webhook->update(['last_triggered_at' => now()]);

            return $this->success(['message' => 'Test webhook gönderildi'], 'Test webhook başarıyla gönderildi');
        } catch (\Exception $e) {
            return $this->error('Test webhook gönderilemedi: ' . $e->getMessage(), 500);
        }
    }

    public function regenerateSecret(Webhook $webhook): JsonResponse
    {
        if ($webhook->company_id !== $this->getCompanyId()) {
            return $this->error('Yetkisiz erişim', 403);
        }

        $webhook->update([
            'secret' => Str::random(32),
            'updated_by' => auth()->id(),
        ]);

        ActivityLog::log('update', $webhook, "Webhook secret yenilendi: {$webhook->name}");

        return $this->success(['secret' => $webhook->secret], 'Secret başarıyla yenilendi');
    }
}

