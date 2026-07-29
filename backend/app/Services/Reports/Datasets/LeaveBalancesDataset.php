<?php

namespace App\Services\Reports\Datasets;

use App\Models\LeaveBalance;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

final class LeaveBalancesDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'leave_balances';
    }

    public function label(): string
    {
        return 'İzin Bakiyeleri';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.leave_balances';
    }

    public function modelClass(): string
    {
        return LeaveBalance::class;
    }

    public function customEntityType(): ?string
    {
        return null;
    }

    public function dataScopeMode(): string
    {
        return 'user';
    }

    public function defaultSort(): array
    {
        return ['year'];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'leave_balances';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('user_id', "{$t}.user_id", 'number', 'Kullanıcı ID'),
            $this->dim('leave_type_id', "{$t}.leave_type_id", 'number', 'İzin Türü ID'),
            $this->dim('year', "{$t}.year", 'number', 'Yıl'),
            $this->measure('total_days', "{$t}.total_days", 'number', 'Toplam'),
            $this->measure('used_days', "{$t}.used_days", 'number', 'Kullanılan'),
            $this->measure('pending_days', "{$t}.pending_days", 'number', 'Bekleyen'),
            $this->measure('carried_over', "{$t}.carried_over", 'number', 'Devreden'),
        ];
    }
}
