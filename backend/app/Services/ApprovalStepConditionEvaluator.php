<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Adım koşulu — güvenli whitelist (eval YOK).
 * Örnek: {"field":"total_days","op":">","value":10}
 */
class ApprovalStepConditionEvaluator
{
    /** @var list<string> */
    private const ALLOWED_FIELDS = [
        'total_days',
        'leave_type_id',
        'user_id',
        'requester_id',
        'amount',
        'department_id',
    ];

    /** @var list<string> */
    private const ALLOWED_OPS = [
        '>',
        '<',
        '=',
        '==',
        '>=',
        '<=',
        'in',
    ];

    /** @return list<string> */
    public static function allowedFields(): array
    {
        return self::ALLOWED_FIELDS;
    }

    /** @return list<string> */
    public static function allowedOps(): array
    {
        return self::ALLOWED_OPS;
    }

    /**
     * @param  array<string, mixed>|null  $condition
     * @param  array<string, mixed>  $context
     */
    public function matches(?array $condition, Model $approvable, array $context = []): bool
    {
        if ($condition === null || $condition === []) {
            return true;
        }

        // Tek koşul veya liste (AND)
        $conditions = array_is_list($condition) && isset($condition[0]) && is_array($condition[0])
            ? $condition
            : [$condition];

        foreach ($conditions as $rule) {
            if (! is_array($rule) || ! $this->evaluateRule($rule, $approvable, $context)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $rule
     * @param  array<string, mixed>  $context
     */
    protected function evaluateRule(array $rule, Model $approvable, array $context): bool
    {
        $field = $rule['field'] ?? null;
        $op = $rule['op'] ?? $rule['operator'] ?? null;
        $expected = $rule['value'] ?? null;

        if (! is_string($field) || ! in_array($field, self::ALLOWED_FIELDS, true)) {
            throw new InvalidArgumentException("approval.condition.invalid_field: {$field}");
        }

        if (! is_string($op) || ! in_array($op, self::ALLOWED_OPS, true)) {
            throw new InvalidArgumentException("approval.condition.invalid_op: {$op}");
        }

        $actual = $context[$field] ?? $approvable->getAttribute($field);

        if ($field === 'requester_id' && $actual === null) {
            $actual = $approvable->getAttribute('user_id');
        }

        if ($actual === null) {
            return false;
        }

        return match ($op) {
            '=' , '==' => $this->looseEquals($actual, $expected),
            '>' => (float) $actual > (float) $expected,
            '>=' => (float) $actual >= (float) $expected,
            '<' => (float) $actual < (float) $expected,
            '<=' => (float) $actual <= (float) $expected,
            'in' => in_array($actual, (array) $expected, false),
            default => false,
        };
    }

    protected function looseEquals(mixed $actual, mixed $expected): bool
    {
        return (string) $actual === (string) $expected;
    }
}
