<?php

namespace App\Services\Reports\Expression;

final class NumberNode extends ExprNode
{
    public function __construct(public readonly float|int $value) {}
}
