<?php

namespace App\Services\Reports\Datasets;

use App\Models\Asset;
use App\Models\CustomFieldDefinition;
use App\Services\Reports\AbstractDataset;
use App\Services\Reports\ReportField;

final class AssetsDataset extends AbstractDataset
{
    public function key(): string
    {
        return 'assets';
    }

    public function label(): string
    {
        return 'Varlıklar / Zimmet';
    }

    public function labelKey(): string
    {
        return 'reports.datasets.assets';
    }

    public function modelClass(): string
    {
        return Asset::class;
    }

    public function customEntityType(): ?string
    {
        return CustomFieldDefinition::ENTITY_ASSET;
    }

    public function dataScopeMode(): string
    {
        return 'company_only';
    }

    public function allowedJoins(): array
    {
        return [
            [
                'key' => 'asset_categories',
                'table' => 'asset_categories',
                'first' => 'assets.category_id',
                'operator' => '=',
                'second' => 'asset_categories.id',
                'type' => 'left',
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
        $t = 'assets';

        return [
            $this->dim('id', "{$t}.id", 'number', 'ID'),
            $this->dim('asset_code', "{$t}.asset_code", 'string', 'Varlık Kodu'),
            $this->dim('name', "{$t}.name", 'string', 'Ad'),
            $this->dim('status', "{$t}.status", 'string', 'Durum'),
            $this->dim('condition', "{$t}.condition", 'string', 'Kondisyon'),
            $this->dim('category_name', 'asset_categories.name', 'string', 'Kategori'),
            $this->dim('brand', "{$t}.brand", 'string', 'Marka'),
            $this->dim('model', "{$t}.model", 'string', 'Model'),
            $this->dim('purchase_date', "{$t}.purchase_date", 'date', 'Alım Tarihi'),
            $this->measure(
                'purchase_price',
                "{$t}.purchase_price",
                'number',
                'Alım Fiyatı',
                null,
                true,
                ReportField::SENSITIVITY_PERSONAL
            ),
            $this->dim('location', "{$t}.location", 'string', 'Lokasyon'),
            $this->dim('warranty_end_date', "{$t}.warranty_end_date", 'date', 'Garanti Bitiş'),
        ];
    }
}
