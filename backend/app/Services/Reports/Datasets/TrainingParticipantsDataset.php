<?php

namespace App\Services\Reports\Datasets;

use App\Models\TrainingParticipant;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

final class TrainingParticipantsDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'training_participants';
    }

    public function label(): string
    {
        return 'Eğitim Katılımları';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.training_participants';
    }

    public function modelClass(): string
    {
        return TrainingParticipant::class;
    }

    public function dataScopeMode(): string
    {
        return 'user';
    }

    public function constrainQuery(\Illuminate\Database\Eloquent\Builder $query, \App\Models\User $user): void
    {
        $companyIds = $this->resolvedCompanyIds($user);
        $query->whereExists(function ($q) use ($companyIds) {
            $q->selectRaw('1')
                ->from('training_sessions')
                ->join('trainings', 'training_sessions.training_id', '=', 'trainings.id')
                ->whereColumn('training_sessions.id', 'training_participants.session_id')
                ->whereIn('trainings.company_id', $companyIds);
        });
    }

    public function tenantCompanyColumn(): ?string
    {
        return null;
    }

    public function allowedJoins(): array
    {
        return [
            [
                'key' => 'training_sessions',
                'table' => 'training_sessions',
                'first' => 'training_participants.session_id',
                'operator' => '=',
                'second' => 'training_sessions.id',
                'type' => 'inner',
            ],
            [
                'key' => 'trainings',
                'table' => 'trainings',
                'first' => 'training_sessions.training_id',
                'operator' => '=',
                'second' => 'trainings.id',
                'type' => 'inner',
            ],
        ];
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
        $t = 'training_participants';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('company_id', 'trainings.company_id', 'number', 'Şirket ID'),
            $this->dim('user_id', "{$t}.user_id", 'number', 'Kullanıcı ID'),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->measure('score', "{$t}.score", 'number', 'Puan'),
            $this->dim('passed', "{$t}.passed", 'boolean', 'Başarılı'),
            $this->dim('registered_at', "{$t}.registered_at", 'date', 'Kayıt'),
            $this->dim('completed_at', "{$t}.completed_at", 'date', 'Tamamlama'),
            $this->dim('training_title', 'trainings.title', 'string', 'Eğitim'),
            $this->dim('training_category', 'trainings.category', 'string', 'Kategori'),
            $this->measure('duration_hours', 'trainings.duration_hours', 'number', 'Süre (saat)'),
        ];
    }
}
