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

    public function personDistinctColumn(): ?string
    {
        return 'leave_requests.user_id';
    }

    public function allowedJoins(): array
    {
        return [
            [
                'key' => 'leave_types',
                'table' => 'leave_types',
                'first' => 'leave_requests.leave_type_id',
                'operator' => '=',
                'second' => 'leave_types.id',
                'type' => 'left',
            ],
        ];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'leave_requests';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('company_id', "{$t}.company_id", 'number', 'Şirket ID'),
            $this->dim('user_id', "{$t}.user_id", 'number', 'Kullanıcı ID'),
            $this->dim('leave_type_id', "{$t}.leave_type_id", 'number', 'İzin Türü ID'),
            $this->dim('leave_type_name', 'leave_types.name', 'string', 'İzin Türü'),
            $this->dim(
                'leave_type_system_code',
                'leave_types.system_code',
                'string',
                'İzin Sistem Kodu',
                null,
                true,
                ReportField::SENSITIVITY_SPECIAL
            ),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->dim('start_date', "{$t}.start_date", 'date', 'Başlangıç'),
            $this->dim('end_date', "{$t}.end_date", 'date', 'Bitiş'),
            $this->measure('total_days', "{$t}.total_days", 'number', 'Toplam Gün'),
            $this->dim('reason', "{$t}.reason", 'string', 'Gerekçe', null, true, ReportField::SENSITIVITY_SPECIAL),
            $this->dim('approved_at', "{$t}.approved_at", 'date', 'Onay Tarihi'),
        ];
    }
}
