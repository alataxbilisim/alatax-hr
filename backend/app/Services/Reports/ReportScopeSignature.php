<?php

namespace App\Services\Reports;

use App\Enums\DataScopeLevel;
use App\Models\User;
use App\Services\DataScopeService;

/**
 * D1f — Cache anahtarı için kullanıcı kapsam imzası.
 * company_id + user/scope + alan izin seti olmadan cache sızıntısı olur.
 */
final class ReportScopeSignature
{
    public function __construct(
        protected DataScopeService $dataScope,
        protected DatasetRegistry $registry,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public function build(User $user, int $companyId, array $config): string
    {
        $scope = $this->dataScope->resolve($user);
        $scopePayload = [
            'company_id' => $companyId,
            'user_id' => (int) $user->id,
            'data_scope' => $scope->value,
            'scope_values' => $this->scopeValues($user, $scope),
            'field_perms' => $this->fieldPermissionSignature($user, $companyId, $config),
        ];

        return hash('sha256', json_encode($scopePayload, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<string, mixed>
     */
    private function scopeValues(User $user, DataScopeLevel $scope): array
    {
        $employee = $user->employee;

        return match ($scope) {
            DataScopeLevel::Company => [],
            DataScopeLevel::Own => ['user_id' => (int) $user->id],
            DataScopeLevel::Department => ['department_id' => $employee?->department_id],
            DataScopeLevel::Branch => ['branch_id' => $employee?->branch_id],
            DataScopeLevel::Team => ['manager_employee_id' => $employee?->id],
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string>
     */
    private function fieldPermissionSignature(User $user, int $companyId, array $config): array
    {
        $datasetKey = (string) ($config['dataset'] ?? '');
        if ($datasetKey === '' || ! $this->registry->has($datasetKey)) {
            return [];
        }
        $fields = $this->registry->get($datasetKey)->fieldsForCompany($companyId);
        $perms = [];
        foreach ($fields as $f) {
            if ($f->permission === null) {
                continue;
            }
            $perms[] = $f->permission.':'.($user->can($f->permission) ? '1' : '0');
        }
        sort($perms);

        return $perms;
    }
}
