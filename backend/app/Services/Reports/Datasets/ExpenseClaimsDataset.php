<?php

namespace App\Services\Reports\Datasets;

use App\Models\CustomFieldDefinition;
use App\Models\ExpenseClaim;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

final class ExpenseClaimsDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'expense_claims';
    }

    public function label(): string
    {
        return 'Masraf Talepleri';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.expense_claims';
    }

    public function modelClass(): string
    {
        return ExpenseClaim::class;
    }

    public function customEntityType(): ?string
    {
        return CustomFieldDefinition::ENTITY_EXPENSE;
    }

    public function dataScopeMode(): string
    {
        return 'user';
    }

    public function defaultSort(): array
    {
        return ['expense_date'];
    }

    /**
     * @return list<ReportField>
     */
    protected function baseFields(): array
    {
        $t = 'expense_claims';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('user_id', "{$t}.user_id", 'number', 'Kullanıcı ID'),
            $this->dim('claim_number', "{$t}.claim_number", 'string', 'Talep No'),
            $this->dim('title', "{$t}.title", 'string', 'Başlık'),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->dim('expense_date', "{$t}.expense_date", 'date', 'Masraf Tarihi'),
            $this->measure('total_amount', "{$t}.total_amount", 'number', 'Tutar'),
            $this->dim('currency', "{$t}.currency", 'string', 'Para Birimi'),
            $this->dim('submitted_at', "{$t}.submitted_at", 'date', 'Gönderim'),
            $this->dim('approved_at', "{$t}.approved_at", 'date', 'Onay'),
            $this->dim('paid_at', "{$t}.paid_at", 'date', 'Ödeme'),
        ];
    }
}
