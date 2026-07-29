<?php

namespace App\Services\Reports\Expression;

final class IfNode extends ExprNode
{
    public function __construct(
        public readonly ExprNode $condition,
        public readonly ExprNode $then,
        public readonly ExprNode $else,
    ) {}
}
