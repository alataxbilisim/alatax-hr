<?php

namespace App\Http\Controllers\Api\V1\Settings;

use App\Http\Controllers\Api\V1\BaseController;
use App\Http\Requests\Settings\ImportSettingsProfileRequest;
use App\Http\Requests\Settings\ResetSettingValueRequest;
use App\Http\Requests\Settings\UpdateSettingValuesRequest;
use App\Models\User;
use App\Services\Settings\SettingsRegistry;
use App\Services\Settings\SettingsResolver;
use App\Services\Settings\SettingsWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsRegistryController extends BaseController
{
    public function __construct(
        protected SettingsRegistry $registry,
        protected SettingsResolver $resolver,
        protected SettingsWriter $writer,
    ) {}

    public function registry(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('settings.values.view') && ! $user->isSuperAdmin()) {
            return $this->error('Yetkisiz', 403);
        }

        $pageKey = $request->query('page_key');
        $pageKey = is_string($pageKey) && $pageKey !== '' ? $pageKey : null;
        $includeAdvanced = filter_var($request->query('include_advanced', true), FILTER_VALIDATE_BOOLEAN);

        $scope = $this->buildScope($request, $user);
        $items = [];
        foreach ($this->registry->visibleFor($user, $pageKey, $includeAdvanced) as $def) {
            $resolved = $this->resolver->resolve($def->key, $scope);
            $items[] = $def->toCatalogArray(
                $resolved['value'],
                $resolved['resolved_from'],
                $resolved['is_default'],
            ) + [
                'can_edit' => $this->registry->userCanEdit($user, $def),
                'legal_min' => $this->resolver->legalMin($def),
                'legal_max' => $this->resolver->legalMax($def),
            ];
        }

        return $this->success($items, 'Ayar kataloğu');
    }

    public function values(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('settings.values.view') && ! $user->isSuperAdmin()) {
            return $this->error('Yetkisiz', 403);
        }

        $keys = $request->query('keys');
        $keyList = is_string($keys) ? array_filter(array_map('trim', explode(',', $keys))) : [];
        $scope = $this->buildScope($request, $user);
        $out = [];

        foreach ($keyList as $key) {
            if (! $this->registry->has($key)) {
                continue;
            }
            $def = $this->registry->get($key);
            if (! $this->registry->userCanView($user, $def)) {
                continue;
            }
            $resolved = $this->resolver->resolve($key, $scope);
            $out[$key] = $resolved;
        }

        return $this->success($out, 'Ayar değerleri');
    }

    public function updateValues(UpdateSettingValuesRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $scope = [
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'] ?? null,
            'company_id' => $data['company_id'] ?? $user->company_id,
        ];

        $updated = [];
        foreach ($data['values'] as $item) {
            $row = $this->writer->put($user, $item['key'], $item['value'], $scope);
            $updated[] = [
                'key' => $row->key,
                'value' => $row->scalarValue(),
                'scope_type' => $row->scope_type->value,
                'scope_id' => $row->scope_id,
            ];
        }

        return $this->success($updated, 'Ayarlar kaydedildi');
    }

    public function reset(ResetSettingValueRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $this->writer->reset($user, $data['key'], [
            'scope_type' => $data['scope_type'],
            'scope_id' => $data['scope_id'] ?? null,
            'company_id' => $data['company_id'] ?? $user->company_id,
        ]);

        return $this->success(null, 'Ayar varsayılana sıfırlandı');
    }

    public function exportProfile(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->can('settings.values.view') && ! $user->isSuperAdmin()) {
            return $this->error('Yetkisiz', 403);
        }
        $companyId = (int) ($request->query('company_id') ?? $user->company_id);
        $profile = $this->writer->exportProfile($user, $companyId);

        return $this->success($profile, 'Ayar profili');
    }

    public function importProfile(ImportSettingsProfileRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validated();
        $companyId = (int) ($data['company_id'] ?? $user->company_id);
        $written = $this->writer->importProfile($user, $companyId, $data['values']);

        return $this->success(['written' => $written], 'Ayar profili içe aktarıldı');
    }

    /**
     * @return array{company_id?: int|null, user_id?: int|null, department_id?: int|null, branch_id?: int|null}
     */
    private function buildScope(Request $request, User $user): array
    {
        $companyId = (int) ($request->query('company_id') ?? $user->company_id);
        if ($user->company_id && (int) $user->company_id !== $companyId && ! $user->isSuperAdmin()) {
            $companyId = (int) $user->company_id;
        }

        $scope = ['company_id' => $companyId ?: null];

        if ($request->filled('user_id')) {
            $scope['user_id'] = (int) $request->query('user_id');
        }
        if ($request->filled('department_id')) {
            $scope['department_id'] = (int) $request->query('department_id');
        }
        if ($request->filled('branch_id')) {
            $scope['branch_id'] = (int) $request->query('branch_id');
        }

        return $scope;
    }
}
