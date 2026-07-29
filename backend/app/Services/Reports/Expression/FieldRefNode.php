<?php

namespace App\Services\Reports\Expression;

final class FieldRefNode extends ExprNode
{
    public function __construct(public readonly string $key) {}
}
