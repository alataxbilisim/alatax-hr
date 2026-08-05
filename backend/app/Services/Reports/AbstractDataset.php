<?php

namespace App\Services\Reports;

use App\Models\CustomFieldDefinition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Dataset sözleşmesi — semantic layer kaydı.
 */
abstract class AbstractDataset
{
    /** @var list<int>|null Rapor çalıştırması için çözülmüş şirket kümesi (G2). */
    private ?array $resolvedCompanyIds = null;

    abstract public function key(): string;

    abstract public function label(): string;

    abstract public function labelKey(): string;

    /** @return class-string<Model> */
    abstract public function modelClass(): string;

    /**
     * Sabit (kod) alanlar — custom alanlar runtime eklenir.
     *
     * @return list<ReportField>
     */
    abstract protected function baseFields(): array;

    /**
     * CustomFieldDefinition entity_type; null = custom yok.
     */
    public function customEntityType(): ?string
    {
        return null;
    }

    /**
     * JSONB kolon adı (custom_fields | form_data).
     */
    public function customJsonColumn(): string
    {
        return 'custom_fields';
    }

    /**
     * DataScope uygulama stratejisi: employee | user | assigned_to | company_only
     */
    abstract public function dataScopeMode(): string;

    /**
     * @return list<array{key: string, table: string, first: string, operator: string, second: string, type: string}>
     */
    public function allowedJoins(): array
    {
        return [];
    }

    /**
     * @return list<string> field keys
     */
    public function defaultSort(): array
    {
        return ['id'];
    }

    public function table(): string
    {
        /** @var Model $model */
        $model = new ($this->modelClass());

        return $model->getTable();
    }

    /**
     * @return list<ReportField>
     */
    public function fieldsForCompany(int $companyId): array
    {
        $fields = $this->baseFields();
        $entity = $this->customEntityType();
        if ($entity === null) {
            return $fields;
        }

        $customs = CustomFieldDefinition::withoutGlobalScopes()
            ->where('entity_type', $entity)
            ->where('is_active', true)
            ->where('is_system', false)
            ->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            })
            ->orderBy('sort_order')
            ->get();

        $jsonCol = $this->customJsonColumn();
        $table = $this->table();

        foreach ($customs as $def) {
            $key = (string) $def->field_key;
            if ($key === '' || ! preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $key)) {
                continue;
            }
            // Firma override varsa sistem null kaydı atlanır
            if ($def->company_id === null && $customs->contains(fn ($c) => $c->company_id === $companyId && $c->field_key === $key)) {
                continue;
            }

            $fields[] = new ReportField(
                key: 'cf_'.$key,
                column: "{$table}.{$jsonCol}",
                type: $this->mapCustomType((string) $def->field_type),
                label: (string) ($def->label_override ?: $def->field_label),
                labelKey: 'reports.fields.custom.'.$key,
                role: in_array($def->field_type, ['number', 'currency'], true) ? 'measure' : 'dimension',
                permission: null,
                sensitive: false,
                isCustom: true,
                customJsonKey: $key,
                customJsonColumn: $jsonCol,
                sensitivity: ReportField::SENSITIVITY_NORMAL,
            );
        }

        return $fields;
    }

    /**
     * @param  list<ReportField>  $fields
     * @return list<ReportField>
     */
    public function filterAllowedFields(array $fields, User $user): array
    {
        return array_values(array_filter($fields, function (ReportField $field) use ($user) {
            if ($field->permission === null) {
                return true;
            }

            return $user->can($field->permission);
        }));
    }

    public function findField(string $key, int $companyId): ?ReportField
    {
        foreach ($this->fieldsForCompany($companyId) as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return Builder<Model>
     */
    public function newQuery(): Builder
    {
        return ($this->modelClass())::query();
    }

    /**
     * Drill-down hiyerarşileri (D1c).
     *
     * @return array<string, array{label: string, levels: list<string>}>
     */
    public function hierarchies(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(User $user, int $companyId): array
    {
        $fields = $this->filterAllowedFields($this->fieldsForCompany($companyId), $user);

        return [
            'key' => $this->key(),
            'label' => $this->label(),
            'label_key' => $this->labelKey(),
            'default_sort' => $this->defaultSort(),
            'joins' => array_map(fn ($j) => [
                'key' => $j['key'],
                'table' => $j['table'],
            ], $this->allowedJoins()),
            'hierarchies' => $this->hierarchies(),
            'fields' => array_map(fn (ReportField $f) => $f->toArray(), $fields),
            'field_count' => count($fields),
        ];
    }

    protected function mapCustomType(string $fieldType): string
    {
        return match ($fieldType) {
            'number', 'currency' => 'number',
            'date', 'datetime' => 'date',
            'boolean', 'checkbox' => 'boolean',
            default => 'string',
        };
    }

    /**
     * Dataset'e özel ek kısıt (ör. join üzerinden company_id).
     *
     * @param  Builder<Model>  $query
     */
    public function constrainQuery(Builder $query, User $user): void
    {
        // varsayılan: yok
    }

    /**
     * Doğrudan ana tabloda company_id filtresi uygulanacak kolon.
     * null = yalnız constrainQuery (survey/training gibi).
     */
    public function tenantCompanyColumn(): ?string
    {
        return $this->table().'.company_id';
    }

    /**
     * @param  list<int>  $ids
     */
    public function setResolvedCompanyIds(array $ids): void
    {
        $normalized = array_values(array_unique(array_map(static fn ($id) => (int) $id, $ids)));
        sort($normalized);
        $this->resolvedCompanyIds = $normalized;
    }

    public function clearResolvedCompanyIds(): void
    {
        $this->resolvedCompanyIds = null;
    }

    /**
     * @return list<int>
     */
    public function resolvedCompanyIds(User $user): array
    {
        if ($this->resolvedCompanyIds !== null && $this->resolvedCompanyIds !== []) {
            return $this->resolvedCompanyIds;
        }

        return [$this->activeCompanyId($user)];
    }

    /**
     * Aktif operasyonel şirket — CompanyContext; yoksa home company_id.
     */
    protected function activeCompanyId(User $user): int
    {
        return (int) (\App\Support\CompanyContext::id() ?? $user->company_id);
    }

    /**
     * DISTINCT kişi sayısı için kolon (min hücre guard). null = guard uygulanmaz.
     */
    public function personDistinctColumn(): ?string
    {
        return null;
    }

    /**
     * Detay drill (satır listesi) bu dataset'te kapalı mı? (anonymous_source)
     */
    public function allowsDetailDrill(): bool
    {
        return true;
    }

    protected function dim(
        string $key,
        string $column,
        string $type,
        string $label,
        ?string $permission = null,
        bool $sensitive = false,
        string $sensitivity = ReportField::SENSITIVITY_NORMAL,
    ): ReportField {
        if ($sensitive && $sensitivity === ReportField::SENSITIVITY_NORMAL) {
            $sensitivity = ReportField::SENSITIVITY_PERSONAL;
        }

        return new ReportField(
            key: $key,
            column: $column,
            type: $type,
            label: $label,
            labelKey: 'reports.fields.'.$this->key().'.'.$key,
            role: 'dimension',
            permission: $permission,
            sensitive: $sensitive || $sensitivity !== ReportField::SENSITIVITY_NORMAL,
            sensitivity: $sensitivity,
        );
    }

    protected function measure(
        string $key,
        string $column,
        string $type,
        string $label,
        ?string $permission = null,
        bool $sensitive = false,
        string $sensitivity = ReportField::SENSITIVITY_NORMAL,
    ): ReportField {
        if ($sensitive && $sensitivity === ReportField::SENSITIVITY_NORMAL) {
            $sensitivity = ReportField::SENSITIVITY_PERSONAL;
        }

        return new ReportField(
            key: $key,
            column: $column,
            type: $type,
            label: $label,
            labelKey: 'reports.fields.'.$this->key().'.'.$key,
            role: 'measure',
            permission: $permission,
            sensitive: $sensitive || $sensitivity !== ReportField::SENSITIVITY_NORMAL,
            sensitivity: $sensitivity,
        );
    }
}
