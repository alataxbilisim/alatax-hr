<?php

namespace App\Services\Settings;

use App\Enums\SettingScopeType;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\SettingValue;
use App\Models\User;
use App\Services\CompanyContextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ayar yazma / reset / profil — yasal taban + yetki.
 */
class SettingsWriter
{
    public function __construct(
        protected SettingsRegistry $registry,
        protected SettingsResolver $resolver,
    ) {}

    /**
     * @param  array{scope_type: string, scope_id?: int|null, company_id?: int|null}  $scope
     */
    public function put(User $actor, string $key, mixed $value, array $scope): SettingValue
    {
        $def = $this->registry->get($key);

        if (! $this->registry->userCanEdit($actor, $def)) {
            abort(403, 'Bu ayarı düzenleme yetkiniz yok.');
        }

        $scopeType = SettingScopeType::from($scope['scope_type']);
        if (! in_array($scopeType, $def->scopeLevels, true)) {
            throw ValidationException::withMessages([
                'scope_type' => ['Bu ayar seçilen kapsamda tanımlanamaz.'],
            ]);
        }

        if ($scopeType === SettingScopeType::System && $actor->type !== UserType::SuperAdmin) {
            abort(403, 'Sistem ayarlarını yalnızca SuperAdmin değiştirebilir.');
        }

        $companyId = $scopeType === SettingScopeType::System
            ? null
            : (int) ($scope['company_id'] ?? $actor->home_company_id);

        if ($scopeType !== SettingScopeType::System && ! $companyId) {
            throw ValidationException::withMessages([
                'company_id' => ['Firma kapsamı gerekli.'],
            ]);
        }

        $this->assertCanAccessCompany($actor, $companyId);

        $scopeId = $scopeType === SettingScopeType::Company || $scopeType === SettingScopeType::System
            ? null
            : (isset($scope['scope_id']) ? (int) $scope['scope_id'] : null);

        if (in_array($scopeType, [SettingScopeType::Branch, SettingScopeType::Department, SettingScopeType::User], true) && ! $scopeId) {
            throw ValidationException::withMessages([
                'scope_id' => ['Bu kapsam için scope_id zorunlu.'],
            ]);
        }

        $casted = $this->castAndValidate($def, $value);

        return DB::transaction(function () use ($def, $casted, $scopeType, $scopeId, $companyId, $actor) {
            $row = SettingValue::query()->updateOrCreate(
                [
                    'company_id' => $companyId,
                    'scope_type' => $scopeType->value,
                    'scope_id' => $scopeId,
                    'key' => $def->key,
                ],
                [
                    'value' => SettingValue::wrapScalar($casted),
                    'updated_by' => $actor->id,
                ]
            );

            if ($def->legacyCompanyPath && $scopeType === SettingScopeType::Company && $companyId) {
                $company = Company::query()->find($companyId);
                if ($company) {
                    $company->setSetting($def->legacyCompanyPath, $casted);
                }
            }

            if ($companyId) {
                $this->resolver->invalidateCompany($companyId);
            } else {
                $this->resolver->invalidateSystem();
            }

            return $row->fresh();
        });
    }

    /**
     * @param  array{scope_type: string, scope_id?: int|null, company_id?: int|null}  $scope
     */
    public function reset(User $actor, string $key, array $scope): void
    {
        $def = $this->registry->get($key);
        if (! $this->registry->userCanEdit($actor, $def)) {
            abort(403, 'Bu ayarı sıfırlama yetkiniz yok.');
        }

        $scopeType = SettingScopeType::from($scope['scope_type']);
        $companyId = $scopeType === SettingScopeType::System
            ? null
            : (int) ($scope['company_id'] ?? $actor->home_company_id);
        $scopeId = $scopeType === SettingScopeType::Company || $scopeType === SettingScopeType::System
            ? null
            : (isset($scope['scope_id']) ? (int) $scope['scope_id'] : null);

        $this->assertCanAccessCompany($actor, $companyId);

        $q = SettingValue::query()
            ->where('key', $key)
            ->where('scope_type', $scopeType->value);

        if ($companyId === null) {
            $q->whereNull('company_id');
        } else {
            $q->where('company_id', $companyId);
        }

        if ($scopeId === null) {
            $q->whereNull('scope_id');
        } else {
            $q->where('scope_id', $scopeId);
        }

        $q->get()->each->delete();

        if ($companyId) {
            $this->resolver->invalidateCompany($companyId);
        } else {
            $this->resolver->invalidateSystem();
        }
    }

    /**
     * @return array{exported_at: string, values: list<array<string, mixed>>}
     */
    public function exportProfile(User $actor, int $companyId): array
    {
        $this->assertCanAccessCompany($actor, $companyId);

        $rows = SettingValue::query()
            ->where('company_id', $companyId)
            ->where('scope_type', SettingScopeType::Company->value)
            ->orderBy('key')
            ->get();

        $values = [];
        foreach ($rows as $row) {
            if (! $this->registry->has($row->key)) {
                continue;
            }
            $def = $this->registry->get($row->key);
            if (! $this->registry->userCanView($actor, $def)) {
                continue;
            }
            $values[] = [
                'key' => $row->key,
                'value' => $row->scalarValue(),
                'scope_type' => $row->scope_type->value,
            ];
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'company_id' => $companyId,
            'values' => $values,
        ];
    }

    /**
     * @param  list<array{key: string, value: mixed, scope_type?: string}>  $values
     * @return list<string> yazılan key'ler
     */
    public function importProfile(User $actor, int $companyId, array $values): array
    {
        $this->assertCanAccessCompany($actor, $companyId);

        $written = [];
        foreach ($values as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key === '' || ! $this->registry->has($key)) {
                continue;
            }
            $def = $this->registry->get($key);
            if (! $this->registry->userCanEdit($actor, $def)) {
                continue;
            }
            if (! in_array(SettingScopeType::Company, $def->scopeLevels, true)) {
                continue;
            }
            $this->put($actor, $key, $item['value'] ?? null, [
                'scope_type' => SettingScopeType::Company->value,
                'company_id' => $companyId,
            ]);
            $written[] = $key;
        }

        return $written;
    }

    private function castAndValidate(SettingDefinition $def, mixed $value): mixed
    {
        $casted = match ($def->type) {
            \App\Enums\SettingValueType::Bool => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
            \App\Enums\SettingValueType::Int, \App\Enums\SettingValueType::Duration => (int) $value,
            \App\Enums\SettingValueType::Decimal, \App\Enums\SettingValueType::Money => (float) $value,
            default => $value,
        };

        $rules = $def->validation ?? [];
        if (($rules['required'] ?? false) && ($casted === null || $casted === '')) {
            throw ValidationException::withMessages([
                'value' => ['Bu ayar zorunludur.'],
            ]);
        }

        if (is_numeric($casted)) {
            if (isset($rules['min']) && (float) $casted < (float) $rules['min']) {
                throw ValidationException::withMessages([
                    'value' => ["Değer en az {$rules['min']} olmalıdır."],
                ]);
            }
            if (isset($rules['max']) && (float) $casted > (float) $rules['max']) {
                throw ValidationException::withMessages([
                    'value' => ["Değer en fazla {$rules['max']} olabilir."],
                ]);
            }

            $legalMin = $this->resolver->legalMin($def);
            if ($legalMin !== null && (float) $casted < $legalMin) {
                throw ValidationException::withMessages([
                    'value' => ["Yasal asgari {$legalMin}."],
                ]);
            }

            $legalMax = $this->resolver->legalMax($def);
            if ($legalMax !== null && (float) $casted > $legalMax) {
                throw ValidationException::withMessages([
                    'value' => ["Yasal azami {$legalMax}."],
                ]);
            }
        }

        if (isset($rules['regex']) && is_string($casted) && ! preg_match($rules['regex'], $casted)) {
            throw ValidationException::withMessages([
                'value' => ['Değer biçimi geçersiz.'],
            ]);
        }

        return $casted;
    }

    /**
     * Home veya membership ile erişilebilir şirket — aktif bağlam yazmayı engellemez.
     */
    private function assertCanAccessCompany(User $actor, ?int $companyId): void
    {
        if ($companyId === null || $actor->type === UserType::SuperAdmin) {
            return;
        }

        if ((int) $actor->home_company_id === $companyId) {
            return;
        }

        if (! app(CompanyContextService::class)->hasMembership($actor, $companyId)) {
            abort(403, 'Başka firmanın ayarlarına erişilemez.');
        }
    }
}
