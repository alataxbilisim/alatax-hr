<?php

namespace App\Services\Reports\Expression;

final class AggCallNode extends ExprNode
{
    /**
     * @param  'sum'|'count'|'count_distinct'|'avg'|'min'|'max'  $fn
     */
    public function __construct(
        public readonly string $fn,
        public readonly ?string $fieldKey,
    ) {}
}
