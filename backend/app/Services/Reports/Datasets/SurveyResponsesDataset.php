<?php

namespace App\Services\Reports\Datasets;

use App\Models\SurveyResponse;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

/**
 * Anket cevapları — anonymous_source (detay drill kapalı).
 */
final class SurveyResponsesDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'survey_responses';
    }

    public function label(): string
    {
        return 'Anket Cevapları';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.survey_responses';
    }

    public function modelClass(): string
    {
        return SurveyResponse::class;
    }

    public function dataScopeMode(): string
    {
        return 'company_only';
    }

    public function personDistinctColumn(): ?string
    {
        return 'survey_submissions.user_id';
    }

    public function allowsDetailDrill(): bool
    {
        return false;
    }

    public function constrainQuery(\Illuminate\Database\Eloquent\Builder $query, \App\Models\User $user): void
    {
        $companyId = $this->activeCompanyId($user);
        $query->whereExists(function ($q) use ($companyId) {
            $q->selectRaw('1')
                ->from('survey_submissions')
                ->join('surveys', 'survey_submissions.survey_id', '=', 'surveys.id')
                ->whereColumn('survey_submissions.id', 'survey_responses.survey_submission_id')
                ->where('surveys.company_id', $companyId);
        });
    }

    public function allowedJoins(): array
    {
        return [
            [
                'key' => 'survey_submissions',
                'table' => 'survey_submissions',
                'first' => 'survey_responses.survey_submission_id',
                'operator' => '=',
                'second' => 'survey_submissions.id',
                'type' => 'inner',
            ],
            [
                'key' => 'surveys',
                'table' => 'surveys',
                'first' => 'survey_submissions.survey_id',
                'operator' => '=',
                'second' => 'surveys.id',
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
        $t = 'survey_responses';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('survey_question_id', "{$t}.survey_question_id", 'number', 'Soru ID'),
            $this->dim('survey_id', 'surveys.id', 'number', 'Anket ID'),
            $this->dim('survey_title', 'surveys.title', 'string', 'Anket'),
            $this->measure(
                'answer_numeric',
                "{$t}.answer_numeric",
                'number',
                'Sayısal Cevap',
                null,
                true,
                ReportField::SENSITIVITY_ANONYMOUS
            ),
            $this->dim(
                'answer_text',
                "{$t}.answer_text",
                'string',
                'Metin Cevap',
                null,
                true,
                ReportField::SENSITIVITY_ANONYMOUS
            ),
        ];
    }
}
