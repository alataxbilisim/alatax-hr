<?php

namespace App\Services\Reports\Expression;

final class LogicNode extends ExprNode
{
    /**
     * @param  'and'|'or'  $op
     */
    public function __construct(
        public readonly string $op,
        public readonly ExprNode $left,
        public readonly ExprNode $right,
    ) {}
}
