<?php

namespace App\Services\Export;

/**
 * Kayıtlı liste/CSV export uçları — DataScope birliği koruması.
 * Yeni export ucu eklenince buraya + ExportEndpointRegistryTest provider'a eklenmeli.
 */
class ExportEndpointRegistry
{
    /**
     * @return list<array{
     *     key: string,
     *     method: string,
     *     path: string,
     *     permission: string,
     *     entity: string
     * }>
     */
    public function definitions(): array
    {
        return [
            [
                'key' => 'employees',
                'method' => 'GET',
                'path' => '/api/v1/employees/export',
                'permission' => 'employees.list.export',
                'entity' => 'employees',
            ],
            [
                'key' => 'users',
                'method' => 'GET',
                'path' => '/api/v1/users/export',
                'permission' => 'management.users.export',
                'entity' => 'users',
            ],
            [
                'key' => 'activity_logs',
                'method' => 'GET',
                'path' => '/api/v1/activity-logs/export',
                'permission' => 'management.audit_logs.export',
                'entity' => 'activity_logs',
            ],
            [
                'key' => 'employee_reports_excel',
                'method' => 'POST',
                'path' => '/api/v1/employees/reports/export/excel',
                'permission' => 'employees.reports.export',
                'entity' => 'employee_reports',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_values(array_map(
            static fn (array $d): string => $d['key'],
            $this->definitions()
        ));
    }

    public function has(string $key): bool
    {
        return in_array($key, $this->keys(), true);
    }

    /**
     * @return array{key: string, method: string, path: string, permission: string, entity: string}
     */
    public function get(string $key): array
    {
        foreach ($this->definitions() as $def) {
            if ($def['key'] === $key) {
                return $def;
            }
        }

        throw new \InvalidArgumentException('Bilinmeyen export ucu: '.$key);
    }
}
