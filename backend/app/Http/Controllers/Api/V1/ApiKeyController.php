<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ApiKeyController extends BaseController
{
    /**
     * API anahtarları listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = ApiKey::where('company_id', $this->getCompanyId())
            ->with('creator:id,name,email')
            ->orderBy('created_at', 'desc');

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Durum filtresi
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $apiKeys = $query->paginate($request->get('per_page', 15));

        return $this->paginated($apiKeys, 'API anahtarları listelendi');
    }

    /**
     * API anahtarı detayı
     */
    public function show(int $id): JsonResponse
    {
        $apiKey = ApiKey::where('company_id', $this->getCompanyId())
            ->with('creator:id,name,email')
            ->find($id);

        if (!$apiKey) {
            return $this->notFound('API anahtarı bulunamadı');
        }

        return $this->success($apiKey);
    }

    /**
     * Yeni API anahtarı oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'sometimes|boolean',
        ]);

        $apiKey = ApiKey::create([
            'company_id' => $this->getCompanyId(),
            'name' => $validated['name'],
            'key' => 'ak_' . Str::random(60),
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => auth()->id(),
        ]);

        ActivityLog::log('create', $apiKey, "API anahtarı oluşturuldu: {$apiKey->name}");

        // Key'i sadece oluşturulduğunda göster
        $response = $apiKey->toArray();
        $response['key'] = $apiKey->key;

        return $this->created($response, 'API anahtarı oluşturuldu');
    }

    /**
     * API anahtarı güncelle
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $apiKey = ApiKey::where('company_id', $this->getCompanyId())->find($id);

        if (!$apiKey) {
            return $this->notFound('API anahtarı bulunamadı');
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:500',
            'permissions' => 'nullable|array',
            'expires_at' => 'nullable|date',
            'is_active' => 'sometimes|boolean',
        ]);

        $oldValues = $apiKey->toArray();
        $apiKey->update($validated);

        ActivityLog::log('update', $apiKey, "API anahtarı güncellendi: {$apiKey->name}", $oldValues, $apiKey->fresh()->toArray());

        return $this->success($apiKey->fresh()->load('creator'), 'API anahtarı güncellendi');
    }

    /**
     * API anahtarı sil
     */
    public function destroy(int $id): JsonResponse
    {
        $apiKey = ApiKey::where('company_id', $this->getCompanyId())->find($id);

        if (!$apiKey) {
            return $this->notFound('API anahtarı bulunamadı');
        }

        $apiKeyName = $apiKey->name;
        $oldValues = $apiKey->toArray();
        $apiKey->delete();

        ActivityLog::log('delete', null, "API anahtarı silindi: {$apiKeyName}", $oldValues, null);

        return $this->success(null, 'API anahtarı silindi');
    }

    /**
     * API anahtarı yeniden oluştur (yeni key)
     */
    public function regenerate(int $id): JsonResponse
    {
        $apiKey = ApiKey::where('company_id', $this->getCompanyId())->find($id);

        if (!$apiKey) {
            return $this->notFound('API anahtarı bulunamadı');
        }

        $oldKey = $apiKey->key;
        $apiKey->update(['key' => 'ak_' . Str::random(60)]);

        ActivityLog::log('update', $apiKey, "API anahtarı yeniden oluşturuldu: {$apiKey->name}");

        $response = $apiKey->toArray();
        $response['key'] = $apiKey->key;

        return $this->success($response, 'API anahtarı yeniden oluşturuldu');
    }
}

