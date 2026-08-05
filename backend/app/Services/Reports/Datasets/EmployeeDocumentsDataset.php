<?php

namespace App\Services\Reports\Datasets;

use App\Enums\DataScopeLevel;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\User;
use App\Services\DataScopeService;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;
use Illuminate\Database\Eloquent\Builder;

final class EmployeeDocumentsDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'employee_documents';
    }

    public function label(): string
    {
        return 'Personel Belgeleri';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.employee_documents';
    }

    public function modelClass(): string
    {
        return EmployeeDocument::class;
    }

    public function dataScopeMode(): string
    {
        // employees.id değil employee_id — kapsam constrainQuery'de
        return 'company_only';
    }

    public function constrainQuery(Builder $query, User $user): void
    {
        $svc = app(DataScopeService::class);
        $level = $svc->resolve($user);
        if ($level === DataScopeLevel::Company || $level === DataScopeLevel::Group) {
            return;
        }
        $empQ = Employee::query()->whereIn('company_id', $this->resolvedCompanyIds($user));
        $svc->scopeForEmployee($empQ, $user);
        $query->whereIn('employee_id', $empQ->pluck('id'));
    }

    public function defaultSort(): array
    {
        return ['id'];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'employee_documents';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('company_id', "{$t}.company_id", 'number', 'Şirket ID'),
            $this->dim('employee_id', "{$t}.employee_id", 'number', 'Personel ID'),
            $this->dim('title', "{$t}.title", 'string', 'Başlık'),
            $this->dim(
                'category',
                "{$t}.category",
                'string',
                'Kategori',
                null,
                true,
                // sağlık belgeleri special
                ReportField::SENSITIVITY_SPECIAL
            ),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->dim('issue_date', "{$t}.issue_date", 'date', 'Düzenleme'),
            $this->dim('expiry_date', "{$t}.expiry_date", 'date', 'Son Geçerlilik'),
            $this->dim('is_expired', "{$t}.is_expired", 'boolean', 'Süresi Dolmuş'),
            $this->dim('is_visible_to_employee', "{$t}.is_visible_to_employee", 'boolean', 'Personele Görünür'),
        ];
    }
}
