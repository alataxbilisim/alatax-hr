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
            [
                'key' => 'positions',
                'table' => 'positions',
                'first' => 'employees.position_id',
                'operator' => '=',
                'second' => 'positions.id',
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
                'levels' => ['branch_name', 'department_name', 'position_id', 'employee_code'],
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
            $this->dim('company_id', "{$t}.company_id", 'number', 'Şirket ID'),
            $this->dim('employee_code', "{$t}.employee_code", 'string', 'Sicil No'),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->dim('department_id', "{$t}.department_id", 'number', 'Departman ID'),
            $this->dim('branch_id', "{$t}.branch_id", 'number', 'Şube ID'),
            $this->dim('position', "{$t}.position", 'string', 'Pozisyon (kod/legacy)'),
            $this->dim('position_id', "{$t}.position_id", 'number', 'Pozisyon ID'),
            $this->dim('position_name', 'positions.name', 'string', 'Pozisyon Adı'),
            $this->dim('title', "{$t}.title", 'string', 'Unvan'),
            $this->dim('hire_date', "{$t}.hire_date", 'date', 'İşe Giriş'),
            $this->dim('contract_type', "{$t}.contract_type", 'string', 'Sözleşme Türü'),
            $this->dim('work_type', "{$t}.work_type", 'string', 'Çalışma Türü'),
            $this->dim('gender', "{$t}.gender", 'string', 'Cinsiyet'),
            $this->dim('city', "{$t}.city", 'string', 'Şehir'),
            $this->dim('national_id', "{$t}.national_id", 'string', 'TCKN', 'employees.list.view', true, ReportField::SENSITIVITY_PERSONAL),
            $this->dim('birth_date', "{$t}.birth_date", 'date', 'Doğum Tarihi', null, true, ReportField::SENSITIVITY_PERSONAL),
            $this->dim('personal_phone', "{$t}.personal_phone", 'string', 'Telefon', null, true, ReportField::SENSITIVITY_PERSONAL),
            $this->dim('address', "{$t}.address", 'string', 'Adres', null, true, ReportField::SENSITIVITY_PERSONAL),
            $this->measure('gross_salary', "{$t}.gross_salary", 'number', 'Brüt Maaş', 'employees.salary.view', true, ReportField::SENSITIVITY_PERSONAL),
            $this->measure('net_salary', "{$t}.net_salary", 'number', 'Net Maaş', 'employees.salary.view', true, ReportField::SENSITIVITY_PERSONAL),
            $this->dim('currency', "{$t}.currency", 'string', 'Para Birimi', 'employees.salary.view', true, ReportField::SENSITIVITY_PERSONAL),
            $this->dim('department_name', 'departments.name', 'string', 'Departman'),
            $this->dim('branch_name', 'branches.name', 'string', 'Şube'),
            $this->dim('position_code', 'positions.code', 'string', 'Pozisyon Kodu'),
        ];
    }

    public function personDistinctColumn(): ?string
    {
        return 'employees.id';
    }
}
