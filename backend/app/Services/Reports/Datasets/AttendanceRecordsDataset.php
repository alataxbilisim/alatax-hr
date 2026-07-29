<?php

namespace App\Services\Reports\Datasets;

use App\Models\AttendanceRecord;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

final class AttendanceRecordsDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'attendance_records';
    }

    public function label(): string
    {
        return 'Puantaj / Devam';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.attendance_records';
    }

    public function modelClass(): string
    {
        return AttendanceRecord::class;
    }

    public function dataScopeMode(): string
    {
        return 'user';
    }

    public function defaultSort(): array
    {
        return ['date'];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'attendance_records';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('user_id', "{$t}.user_id", 'number', 'Kullanıcı ID'),
            $this->dim('date', "{$t}.date", 'date', 'Tarih'),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->measure('total_hours', "{$t}.total_hours", 'number', 'Toplam Saat'),
            $this->measure('overtime_hours', "{$t}.overtime_hours", 'number', 'Fazla Mesai'),
            $this->measure('late_minutes', "{$t}.late_minutes", 'number', 'Geç Kalma (dk)'),
            $this->measure('early_leave_minutes', "{$t}.early_leave_minutes", 'number', 'Erken Çıkış (dk)'),
            $this->measure('missing_minutes', "{$t}.missing_minutes", 'number', 'Eksik (dk)'),
            $this->dim('branch_id', "{$t}.branch_id", 'number', 'Şube ID'),
            $this->dim('is_approved', "{$t}.is_approved", 'boolean', 'Onaylı'),
            $this->dim('source', "{$t}.source", 'string', 'Kaynak'),
        ];
    }
}
