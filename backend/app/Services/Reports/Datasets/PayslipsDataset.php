<?php

namespace App\Services\Reports\Datasets;

use App\Enums\DataScopeLevel;
use App\Models\Employee;
use App\Models\Payslip;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;
use Illuminate\Database\Eloquent\Builder;

final class PayslipsDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'payslips';
    }

    public function label(): string
    {
        return 'Bordrolar';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.payslips';
    }

    public function modelClass(): string
    {
        return Payslip::class;
    }

    public function dataScopeMode(): string
    {
        return 'company_only';
    }

    public function constrainQuery(Builder $query, User $user): void
    {
        $svc = app(DataScopeService::class);
        if ($svc->resolve($user) === DataScopeLevel::Company) {
            return;
        }
        $empQ = Employee::query()->where('company_id', (int) $user->company_id);
        $svc->scopeForEmployee($empQ, $user);
        $query->whereIn('employee_id', $empQ->pluck('id'));
    }

    public function defaultSort(): array
    {
        return ['year', 'month'];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'payslips';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('employee_id', "{$t}.employee_id", 'number', 'Personel ID'),
            $this->dim('period', "{$t}.period", 'string', 'Dönem'),
            $this->dim('year', "{$t}.year", 'number', 'Yıl'),
            $this->dim('month', "{$t}.month", 'number', 'Ay'),
            $this->measure(
                'gross_salary',
                "{$t}.gross_salary",
                'number',
                'Brüt',
                'employees.salary.view',
                true,
                ReportField::SENSITIVITY_PERSONAL
            ),
            $this->measure(
                'net_salary',
                "{$t}.net_salary",
                'number',
                'Net',
                'employees.salary.view',
                true,
                ReportField::SENSITIVITY_PERSONAL
            ),
            $this->measure(
                'total_deductions',
                "{$t}.total_deductions",
                'number',
                'Kesintiler',
                'employees.salary.view',
                true,
                ReportField::SENSITIVITY_PERSONAL
            ),
            $this->measure('worked_days', "{$t}.worked_days", 'number', 'Çalışılan Gün'),
            $this->measure('overtime_hours', "{$t}.overtime_hours", 'number', 'Fazla Mesai'),
            $this->dim('is_published', "{$t}.is_published", 'boolean', 'Yayında'),
            $this->dim('published_at', "{$t}.published_at", 'date', 'Yayın Tarihi'),
        ];
    }
}
