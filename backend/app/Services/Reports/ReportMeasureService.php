<?php

namespace App\Services\Reports;

use App\Models\ReportMeasure;
use App\Models\User;
use App\Services\Reports\Expression\MeasureExpressionCompiler;
use App\Services\Reports\Expression\MeasureExpressionParser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ReportMeasureService
{
    public function __construct(
        protected DatasetRegistry $registry,
        protected MeasureExpressionParser $parser,
        protected MeasureExpressionCompiler $compiler,
        protected ReportQueryBuilder $builder,
        protected ReportResultCache $resultCache,
    ) {}

    public function listFor(int $companyId, ?string $datasetKey, int $perPage = 50): LengthAwarePaginator
    {
        $q = ReportMeasure::query()->where('company_id', $companyId)->orderBy('dataset_key')->orderBy('key');
        if ($datasetKey) {
            $q->where('dataset_key', $datasetKey);
        }

        return $q->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, int $companyId, array $data): ReportMeasure
    {
        $this->assertDataset($data['dataset_key'] ?? null);
        $this->validateExpression($user, $companyId, (string) $data['dataset_key'], (string) $data['expression']);

        $measure = ReportMeasure::create([
            'company_id' => $companyId,
            'dataset_key' => $data['dataset_key'],
            'key' => $data['key'],
            'label' => $data['label'],
            'expression' => $data['expression'],
            'format' => $data['format'] ?? 'number',
            'decimals' => (int) ($data['decimals'] ?? 2),
        ]);
        $this->resultCache->bumpGlobal();

        return $measure;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(ReportMeasure $measure, User $user, array $data): ReportMeasure
    {
        if (isset($data['expression']) || isset($data['dataset_key'])) {
            $ds = (string) ($data['dataset_key'] ?? $measure->dataset_key);
            $expr = (string) ($data['expression'] ?? $measure->expression);
            $this->assertDataset($ds);
            $this->validateExpression($user, (int) $measure->company_id, $ds, $expr);
        }
        foreach (['dataset_key', 'key', 'label', 'expression', 'format'] as $k) {
            if (array_key_exists($k, $data)) {
                $measure->{$k} = $data[$k];
            }
        }
        if (array_key_exists('decimals', $data)) {
            $measure->decimals = (int) $data['decimals'];
        }
        $measure->save();
        $this->resultCache->bumpGlobal();

        return $measure->fresh();
    }

    public function delete(ReportMeasure $measure): void
    {
        $measure->delete();
        $this->resultCache->bumpGlobal();
    }

    /**
     * @return array{ok: bool, error?: string, referenced_fields?: list<string>}
     */
    public function validate(User $user, int $companyId, string $datasetKey, string $expression): array
    {
        try {
            $refs = $this->validateExpression($user, $companyId, $datasetKey, $expression);

            return ['ok' => true, 'referenced_fields' => $refs];
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * @return list<string>
     */
    public function validateExpression(User $user, int $companyId, string $datasetKey, string $expression): array
    {
        $this->assertDataset($datasetKey);
        $dataset = $this->registry->get($datasetKey);
        $allowed = $dataset->filterAllowedFields($dataset->fieldsForCompany($companyId), $user);
        $fieldMap = [];
        foreach ($allowed as $f) {
            $fieldMap[$f->key] = $f;
        }

        $ast = $this->parser->parse($expression);
        $refs = $this->parser->referencedFields($ast);
        foreach ($refs as $ref) {
            if (! isset($fieldMap[$ref])) {
                throw new InvalidArgumentException('Formül izinsiz alana referans veriyor: '.$ref);
            }
        }
        $this->compiler->compile(
            $ast,
            $fieldMap,
            fn ($f) => $this->builder->externalSqlExpression($f)
        );

        return $refs;
    }

    private function assertDataset(mixed $key): void
    {
        if (! is_string($key) || ! $this->registry->has($key)) {
            throw ValidationException::withMessages(['dataset_key' => ['Geçersiz dataset']]);
        }
    }
}
