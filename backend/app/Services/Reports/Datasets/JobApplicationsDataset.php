<?php

namespace App\Services\Reports\Datasets;

use App\Models\CustomFieldDefinition;
use App\Models\JobApplication;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

final class JobApplicationsDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'job_applications';
    }

    public function label(): string
    {
        return 'İş Başvuruları';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.job_applications';
    }

    public function modelClass(): string
    {
        return JobApplication::class;
    }

    public function customEntityType(): ?string
    {
        return CustomFieldDefinition::ENTITY_JOB_APPLICATION;
    }

    public function customJsonColumn(): string
    {
        return 'form_data';
    }

    public function dataScopeMode(): string
    {
        return 'assigned_to';
    }

    public function defaultSort(): array
    {
        return ['created_at'];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'job_applications';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('job_position_id', "{$t}.job_position_id", 'number', 'Pozisyon ID'),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->dim('source', "{$t}.source", 'string', 'Kaynak'),
            $this->dim('first_name', "{$t}.first_name", 'string', 'Ad'),
            $this->dim('last_name', "{$t}.last_name", 'string', 'Soyad'),
            $this->dim('email', "{$t}.email", 'string', 'E-posta'),
            $this->measure('rating', "{$t}.rating", 'number', 'Puan'),
            $this->dim('assigned_to', "{$t}.assigned_to", 'number', 'Atanan'),
            $this->dim('created_at', "{$t}.created_at", 'date', 'Başvuru Tarihi'),
            $this->dim('consent_kvkk', "{$t}.consent_kvkk", 'boolean', 'KVKK Onayı'),
        ];
    }
}
