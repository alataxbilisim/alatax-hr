<?php

namespace App\Services\Reports\Datasets;

use App\Models\CustomFieldDefinition;
use App\Models\LeaveRequest;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

final class LeaveRequestsDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'leave_requests';
    }

    public function label(): string
    {
        return 'İzin Talepleri';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.leave_requests';
    }

    public function modelClass(): string
    {
        return LeaveRequest::class;
    }

    public function customEntityType(): ?string
    {
        return CustomFieldDefinition::ENTITY_LEAVE_REQUEST;
    }

    public function dataScopeMode(): string
    {
        return 'user';
    }

    public function defaultSort(): array
    {
        return ['start_date'];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'leave_requests';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('user_id', "{$t}.user_id", 'number', 'Kullanıcı ID'),
            $this->dim('leave_type_id', "{$t}.leave_type_id", 'number', 'İzin Türü ID'),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->dim('start_date', "{$t}.start_date", 'date', 'Başlangıç'),
            $this->dim('end_date', "{$t}.end_date", 'date', 'Bitiş'),
            $this->measure('total_days', "{$t}.total_days", 'number', 'Toplam Gün'),
            $this->dim('reason', "{$t}.reason", 'string', 'Gerekçe'),
            $this->dim('approved_at', "{$t}.approved_at", 'date', 'Onay Tarihi'),
        ];
    }
}
