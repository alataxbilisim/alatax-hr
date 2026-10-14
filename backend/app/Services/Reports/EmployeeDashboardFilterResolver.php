<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\EmployeeSensitiveFieldService;
use App\Services\Reports\Datasets\EmployeesDataset;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Personel panosu widget filtreleri: istemci anahtarları yalnızca EmployeesDataset'in
 * `employees` tablosundaki sabit alanlarına eşlenir; hassas alanlar alan izni ister.
 */
final class EmployeeDashboardFilterResolver
{
    /** Dataset'te olup panodan filtrelenemeyen alanlar (tenant kolonu sunucuda uygulanır). */
    private const EXCLUDED_KEYS = ['company_id'];

    private const TABLE_PREFIX = 'employees.';

    public function __construct(
        private readonly EmployeesDataset $dataset,
        private readonly EmployeeSensitiveFieldService $sensitiveFields,
    ) {}

    /**
     * @return array<string, ReportField> alan anahtarı => alan
     */
    public function filterableFields(int $companyId): array
    {
        $fields = [];
        foreach ($this->dataset->fieldsForCompany($companyId) as $field) {
            if ($field->isCustom
                || in_array($field->key, self::EXCLUDED_KEYS, true)
                || ! str_starts_with($field->column, self::TABLE_PREFIX)) {
                continue;
            }
            $fields[$field->key] = $field;
        }

        return $fields;
    }

    /**
     * @param  array<array-key, mixed>  $filters
     * @return array<string, scalar|list<scalar>> kolon => değer (boş değerler atlanır)
     *
     * @throws ValidationException bilinmeyen alan veya geçersiz değer (422)
     * @throws AuthorizationException hassas alan için izin yok (403)
     */
    public function resolve(array $filters, User $user, int $companyId): array
    {
        $allowed = $this->filterableFields($companyId);
        $resolved = [];

        foreach ($filters as $key => $value) {
            $key = (string) $key;
            $field = $allowed[$key] ?? null;

            if ($field === null) {
                throw ValidationException::withMessages([
                    "config.filters.{$key}" => __('messages.query.unknown_filter_field', ['field' => $key]),
                ]);
            }

            if (! $this->canFilterBy($field, $user)) {
                throw new AuthorizationException(__('messages.query.forbidden_filter_field', ['field' => $key]));
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            if (! $this->isScalarOrScalarList($value)) {
                throw ValidationException::withMessages([
                    "config.filters.{$key}" => __('messages.query.invalid_filter_value', ['field' => $key]),
                ]);
            }

            $resolved[substr($field->column, strlen(self::TABLE_PREFIX))] = $value;
        }

        return $resolved;
    }

    private function canFilterBy(ReportField $field, User $user): bool
    {
        $column = substr($field->column, strlen(self::TABLE_PREFIX));

        if (in_array($column, EmployeeSensitiveFieldService::SALARY_FIELDS, true) || $field->permission === 'employees.salary.view') {
            return $this->sensitiveFields->canViewSalary($user);
        }

        if (in_array($column, EmployeeSensitiveFieldService::TCKN_FIELDS, true)) {
            return $user->can('employees.tckn.view');
        }

        if ($field->permission !== null && ! $user->can($field->permission)) {
            return false;
        }

        // İzin tanımı olmayan kişisel alanlar (doğum tarihi, telefon, adres) filtreyle sorgulanamaz.
        return ! $field->sensitive || $field->permission !== null;
    }

    private function isScalarOrScalarList(mixed $value): bool
    {
        if (is_scalar($value)) {
            return true;
        }

        if (! is_array($value) || ! array_is_list($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! is_scalar($item)) {
                return false;
            }
        }

        return true;
    }
}
