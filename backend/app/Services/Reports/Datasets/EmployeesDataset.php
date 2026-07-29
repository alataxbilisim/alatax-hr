<?php

namespace App\Services\Reports\Datasets;

use App\Models\CustomFieldDefinition;
use App\Models\Employee;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

final class EmployeesDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'employees';
    }

    public function label(): string
    {
        return 'Personel';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.employees';
    }

    public function modelClass(): string
    {
        return Employee::class;
    }

    public function customEntityType(): ?string
    {
        return CustomFieldDefinition::ENTITY_EMPLOYEE;
    }

    public function dataScopeMode(): string
    {
        return 'employee';
    }

    public function allowedJoins(): array
    {
        return [
            [
                'key' => 'departments',
                'table' => 'departments',
                'first' => 'employees.department_id',
                'operator' => '=',
                'second' => 'departments.id',
                'type' => 'left',
            ],
            [
                'key' => 'branches',
                'table' => 'branches',
                'first' => 'employees.branch_id',
                'operator' => '=',
                'second' => 'branches.id',
                'type' => 'left',
            ],
        ];
    }

    public function defaultSort(): array
    {
        return ['employee_code'];
    }

    public function hierarchies(): array
    {
        return [
            'org' => [
                'label' => 'Organizasyon',
                'levels' => ['branch_name', 'department_name', 'position', 'employee_code'],
            ],
            'hire_date' => [
                'label' => 'İşe giriş tarihi',
                'levels' => ['hire_date'], // grain drill FE/BE date_trunc ile
                'date_field' => 'hire_date',
                'date_grains' => ['year', 'quarter', 'month', 'day'],
            ],
        ];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'employees';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('employee_code', "{$t}.employee_code", 'string', 'Sicil No'),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->dim('department_id', "{$t}.department_id", 'number', 'Departman ID'),
            $this->dim('branch_id', "{$t}.branch_id", 'number', 'Şube ID'),
            $this->dim('position', "{$t}.position", 'string', 'Pozisyon'),
            $this->dim('title', "{$t}.title", 'string', 'Unvan'),
            $this->dim('hire_date', "{$t}.hire_date", 'date', 'İşe Giriş'),
            $this->dim('contract_type', "{$t}.contract_type", 'string', 'Sözleşme Türü'),
            $this->dim('work_type', "{$t}.work_type", 'string', 'Çalışma Türü'),
            $this->dim('gender', "{$t}.gender", 'string', 'Cinsiyet'),
            $this->dim('city', "{$t}.city", 'string', 'Şehir'),
            $this->measure('gross_salary', "{$t}.gross_salary", 'number', 'Brüt Maaş', 'employees.salary.view', true),
            $this->measure('net_salary', "{$t}.net_salary", 'number', 'Net Maaş', 'employees.salary.view', true),
            $this->dim('currency', "{$t}.currency", 'string', 'Para Birimi', 'employees.salary.view', true),
            $this->dim('department_name', 'departments.name', 'string', 'Departman'),
            $this->dim('branch_name', 'branches.name', 'string', 'Şube'),
        ];
    }
}
