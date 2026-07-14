<?php

namespace App\Support;

/**
 * İstek bazlı şube bağlamı (X-Branch-Id).
 * branchId = null → "tüm şubeler" (DataScope tavanı hâlâ geçerli).
 */
final class BranchContext
{
    public function __construct(
        public readonly ?int $branchId,
        public readonly bool $canSelectAll,
        public readonly ?int $lockedBranchId = null,
    ) {}

    public function isAll(): bool
    {
        return $this->branchId === null;
    }

    public static function all(bool $canSelectAll = true): self
    {
        return new self(null, $canSelectAll, null);
    }

    public static function locked(int $branchId): self
    {
        return new self($branchId, false, $branchId);
    }
}
