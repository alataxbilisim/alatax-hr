<?php

namespace App\Services\Reports\Expression;

use App\Services\Reports\ReportField;
use InvalidArgumentException;

/**
 * AST → parametreli SQL fragmanı.
 * Alan/izin kontrolü her derlemede yapılır (çalıştırma anı).
 */
final class MeasureExpressionCompiler
{
    /**
     * @param  array<string, ReportField>  $fieldMap  kullanıcının GÖREBİLDİĞİ alanlar
     * @return array{sql: string, bindings: list<mixed>}
     */
    public function compile(ExprNode $node, array $fieldMap, callable $sqlExpression): array
    {
        $bindings = [];
        $sql = $this->compileNode($node, $fieldMap, $sqlExpression, $bindings);

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    /**
     * @param  array<string, ReportField>  $fieldMap
     * @param  list<mixed>  $bindings
     */
    private function compileNode(ExprNode $node, array $fieldMap, callable $sqlExpression, array &$bindings): string
    {
        if ($node instanceof NumberNode) {
            $bindings[] = $node->value;

            return '?';
        }

        if ($node instanceof FieldRefNode) {
            // Bare field only valid inside agg in measure context — reject bare refs outside agg
            throw new InvalidArgumentException(
                'Alan yalnız aggregation içinde kullanılabilir: '.$node->key
            );
        }

        if ($node instanceof AggCallNode) {
            return $this->compileAgg($node, $fieldMap, $sqlExpression);
        }

        if ($node instanceof BinaryOpNode) {
            $l = $this->compileNode($node->left, $fieldMap, $sqlExpression, $bindings);
            $r = $this->compileNode($node->right, $fieldMap, $sqlExpression, $bindings);
            if ($node->op === '/') {
                return "(({$l}) / NULLIF(({$r}), 0))";
            }

            return "(({$l}) {$node->op} ({$r}))";
        }

        if ($node instanceof CompareNode) {
            $l = $this->compileNode($node->left, $fieldMap, $sqlExpression, $bindings);
            $r = $this->compileNode($node->right, $fieldMap, $sqlExpression, $bindings);
            $sqlOp = match ($node->op) {
                '=' => '=',
                '!=' => '<>',
                '>' => '>',
                '<' => '<',
                '>=' => '>=',
                '<=' => '<=',
                default => throw new InvalidArgumentException('Geçersiz karşılaştırma'),
            };

            return "(({$l}) {$sqlOp} ({$r}))";
        }

        if ($node instanceof LogicNode) {
            $l = $this->compileNode($node->left, $fieldMap, $sqlExpression, $bindings);
            $r = $this->compileNode($node->right, $fieldMap, $sqlExpression, $bindings);
            $op = $node->op === 'and' ? 'AND' : 'OR';

            return "(({$l}) {$op} ({$r}))";
        }

        if ($node instanceof IfNode) {
            $c = $this->compileNode($node->condition, $fieldMap, $sqlExpression, $bindings);
            $t = $this->compileNode($node->then, $fieldMap, $sqlExpression, $bindings);
            $e = $this->compileNode($node->else, $fieldMap, $sqlExpression, $bindings);

            return "(CASE WHEN ({$c}) THEN ({$t}) ELSE ({$e}) END)";
        }

        throw new InvalidArgumentException('Bilinmeyen AST düğümü');
    }

    /**
     * @param  array<string, ReportField>  $fieldMap
     */
    private function compileAgg(AggCallNode $node, array $fieldMap, callable $sqlExpression): string
    {
        if ($node->fn === 'count' && $node->fieldKey === null) {
            return 'COUNT(*)';
        }

        $key = $node->fieldKey;
        if ($key === null || ! isset($fieldMap[$key])) {
            throw new InvalidArgumentException(
                'Aggregation alanı yetkisiz veya bilinmiyor: '.(string) $key
            );
        }

        $field = $fieldMap[$key];
        if ($node->fn !== 'count' && $node->fn !== 'count_distinct' && ! $field->isMeasure()) {
            // count on dimensions allowed; sum/avg/min/max require measure role
            // Actually count_distinct on dimensions is fine; sum on dimension should fail
            if (in_array($node->fn, ['sum', 'avg', 'min', 'max'], true) && ! $field->isMeasure()) {
                throw new InvalidArgumentException('Ölçü olmayan alana aggregation uygulanamaz: '.$key);
            }
        }

        $expr = $sqlExpression($field);

        return match ($node->fn) {
            'sum' => "SUM({$expr})",
            'avg' => "AVG({$expr})",
            'min' => "MIN({$expr})",
            'max' => "MAX({$expr})",
            'count' => "COUNT({$expr})",
            'count_distinct' => "COUNT(DISTINCT {$expr})",
            default => throw new InvalidArgumentException('Bilinmeyen aggregation'),
        };
    }
}
