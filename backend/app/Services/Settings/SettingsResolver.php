<?php

namespace App\Services\Settings;

use App\Enums\SettingScopeType;
use App\Models\Company;
use App\Models\Employee;
use App\Models\SettingValue;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Kapsamlı çözümleme: user → department → branch → company → system → definition default.
 * İstek başına bellek cache + firma bazlı Redis/array cache (versiyonlu invalidation).
 */
class SettingsResolver
{
    /** @var array<string, mixed> */
    private array $requestCache = [];

    public function __construct(
        protected SettingsRegistry $registry,
    ) {}

    /**
     * @param  array{company_id?: int|null, user_id?: int|null, department_id?: int|null, branch_id?: int|null}  $scope
     */
    public function get(string $key, array $scope = []): mixed
    {
        $companyId = $scope['company_id'] ?? null;
        $cacheKey = $this->requestCacheKey($key, $scope);
        if (array_key_exists($cacheKey, $this->requestCache)) {
            return $this->requestCache[$cacheKey];
        }

        $resolved = $this->resolve($key, $scope);
        $this->requestCache[$cacheKey] = $resolved['value'];

        return $resolved['value'];
    }

    /**
     * @param  array{company_id?: int|null, user_id?: int|null, department_id?: int|null, branch_id?: int|null}  $scope
     * @return array{value: mixed, resolved_from: string, is_default: bool}
     */
    public function resolve(string $key, array $scope = []): array
    {
        $def = $this->registry->get($key);
        $companyId = isset($scope['company_id']) ? (int) $scope['company_id'] : null;

        $firmCacheKey = $companyId
            ? $this->firmCacheKey($companyId, $key, $scope)
            : null;

        if ($firmCacheKey !== null) {
            $cached = Cache::get($firmCacheKey);
            if (is_array($cached) && array_key_exists('value', $cached)) {
                return $cached;
            }
        }

        $ids = $this->expandScopeIds($scope);
        $result = null;

        foreach (SettingScopeType::resolutionOrder() as $level) {
            if (! in_array($level, $def->scopeLevels, true) && $level !== SettingScopeType::System) {
                continue;
            }

            $row = match ($level) {
                SettingScopeType::User => $this->findRow($key, SettingScopeType::User, $ids['user_id'], $companyId),
                SettingScopeType::Department => $this->findRow($key, SettingScopeType::Department, $ids['department_id'], $companyId),
                SettingScopeType::Branch => $this->findRow($key, SettingScopeType::Branch, $ids['branch_id'], $companyId),
                SettingScopeType::Company => $companyId
                    ? $this->findRow($key, SettingScopeType::Company, null, $companyId)
                    : null,
                SettingScopeType::System => $this->findRow($key, SettingScopeType::System, null, null),
            };

            if ($row !== null) {
                $result = [
                    'value' => $this->castValue($def, $row->scalarValue()),
                    'resolved_from' => $level->value,
                    'is_default' => false,
                ];
                break;
            }
        }

        // Legacy company.settings köprüsü (pilot rapor gizlilik)
        if ($result === null && $def->legacyCompanyPath && $companyId) {
            $company = Company::query()->find($companyId);
            if ($company) {
                $legacy = $company->getSetting($def->legacyCompanyPath, null);
                if ($legacy !== null) {
                    $result = [
                        'value' => $this->castValue($def, $legacy),
                        'resolved_from' => 'company_legacy',
                        'is_default' => false,
                    ];
                }
            }
        }

        if ($result === null) {
            $result = [
                'value' => $this->castValue($def, $def->default),
                'resolved_from' => 'default',
                'is_default' => true,
            ];
        }

        if ($firmCacheKey !== null) {
            Cache::put($firmCacheKey, $result, now()->addMinutes(30));
        }

        return $result;
    }

    public function forgetRequestCache(): void
    {
        $this->requestCache = [];
    }

    public function invalidateCompany(int $companyId): void
    {
        $verKey = "settings:company:{$companyId}:ver";
        Cache::forever($verKey, (string) microtime(true));
        $this->forgetRequestCache();
    }

    public function invalidateSystem(): void
    {
        Cache::forever('settings:system:ver', (string) microtime(true));
        $this->forgetRequestCache();
    }

    public function legalMin(SettingDefinition $def): ?float
    {
        if ($def->legalMinKey === null) {
            return null;
        }

        $v = $this->get($def->legalMinKey, []);

        return is_numeric($v) ? (float) $v : null;
    }

    public function legalMax(SettingDefinition $def): ?float
    {
        if ($def->legalMaxKey === null) {
            return null;
        }

        $v = $this->get($def->legalMaxKey, []);

        return is_numeric($v) ? (float) $v : null;
    }

    /**
     * @param  array{company_id?: int|null, user_id?: int|null, department_id?: int|null, branch_id?: int|null}  $scope
     * @return array{user_id: int|null, department_id: int|null, branch_id: int|null}
     */
    private function expandScopeIds(array $scope): array
    {
        $userId = isset($scope['user_id']) ? (int) $scope['user_id'] : null;
        $departmentId = isset($scope['department_id']) ? (int) $scope['department_id'] : null;
        $branchId = isset($scope['branch_id']) ? (int) $scope['branch_id'] : null;

        if ($userId && ($departmentId === null || $branchId === null)) {
            $employee = Employee::query()
                ->where('user_id', $userId)
                ->when(isset($scope['company_id']), fn ($q) => $q->where('company_id', (int) $scope['company_id']))
                ->first();
            if ($employee) {
                $departmentId = $departmentId ?? ($employee->department_id ? (int) $employee->department_id : null);
                $branchId = $branchId ?? ($employee->branch_id ? (int) $employee->branch_id : null);
            }
        }

        return [
            'user_id' => $userId,
            'department_id' => $departmentId,
            'branch_id' => $branchId,
        ];
    }

    private function findRow(string $key, SettingScopeType $scopeType, ?int $scopeId, ?int $companyId): ?SettingValue
    {
        $q = SettingValue::query()
            ->where('key', $key)
            ->where('scope_type', $scopeType->value);

        if ($scopeType === SettingScopeType::System) {
            $q->whereNull('company_id')->whereNull('scope_id');
        } elseif ($scopeType === SettingScopeType::Company) {
            $q->where('company_id', $companyId)->whereNull('scope_id');
        } else {
            if ($scopeId === null) {
                return null;
            }
            $q->where('company_id', $companyId)->where('scope_id', $scopeId);
        }

        return $q->first();
    }

    private function castValue(SettingDefinition $def, mixed $value): mixed
    {
        return match ($def->type) {
            \App\Enums\SettingValueType::Bool => (bool) $value,
            \App\Enums\SettingValueType::Int, \App\Enums\SettingValueType::Duration => (int) $value,
            \App\Enums\SettingValueType::Decimal, \App\Enums\SettingValueType::Money => (float) $value,
            \App\Enums\SettingValueType::Json, \App\Enums\SettingValueType::Multiselect => $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function requestCacheKey(string $key, array $scope): string
    {
        return $key.'|'.md5(json_encode($scope, JSON_UNESCAPED_UNICODE) ?: '');
    }

    /**
     * @param  array<string, mixed>  $scope
     */
    private function firmCacheKey(int $companyId, string $key, array $scope): string
    {
        $ver = (string) Cache::get("settings:company:{$companyId}:ver", '0');
        $sysVer = (string) Cache::get('settings:system:ver', '0');

        return 'settings:v:'.$ver.':s:'.$sysVer.':c:'.$companyId.':'.md5($key.'|'.json_encode($scope));
    }

    /**
     * Auth kullanıcıdan kapsam çıkar.
     *
     * @return array{company_id?: int|null, user_id?: int|null, department_id?: int|null, branch_id?: int|null}
     */
    public function scopeFromUser(?User $user): array
    {
        if (! $user) {
            return [];
        }

        // Aktif operasyonel şirket (CompanyContext); yoksa home
        $companyId = \App\Support\CompanyContext::id() ?? $user->company_id;
        if (! $companyId) {
            return [];
        }

        return [
            'company_id' => (int) $companyId,
            'user_id' => (int) $user->id,
        ];
    }
}
