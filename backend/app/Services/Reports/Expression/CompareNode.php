<?php

namespace App\Services\Reports\Expression;

final class CompareNode extends ExprNode
{
    /**
     * @param  '='|'!='|'>'|'<'|'>='|'<='  $op
     */
    public function __construct(
        public readonly string $op,
        public readonly ExprNode $left,
        public readonly ExprNode $right,
    ) {}
}
