<?php

namespace App\Support;

use App\Exceptions\CompanyContextMissingException;
use RuntimeException;

/**
 * İstek / job bazlı operasyonel şirket bağlamı (X-Company-Id).
 * Tek company_id — "all" yok. BelongsToCompany hâlâ "= tek id".
 */
final class CompanyContext
{
    public function __construct(
        public readonly int $companyId,
    ) {}

    public static function id(): ?int
    {
        if (! app()->bound(self::class)) {
            return null;
        }

        return app(self::class)->companyId;
    }

    /**
     * Bağlam zorunlu — yoksa exception (sessiz varsayılan yok).
     */
    public static function requireId(): int
    {
        $id = self::id();
        if ($id === null) {
            throw new CompanyContextMissingException(
                'Company context is required but not set.'
            );
        }

        return $id;
    }

    public static function isBound(): bool
    {
        return app()->bound(self::class);
    }

    /**
     * Geçici bağlam ile çalıştır (job/komut döngüsü).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function run(int $companyId, callable $callback): mixed
    {
        if ($companyId < 1) {
            throw new RuntimeException('Invalid company id for CompanyContext::run.');
        }

        $previous = self::isBound() ? app(self::class) : null;
        app()->instance(self::class, new self($companyId));

        try {
            return $callback();
        } finally {
            if ($previous instanceof self) {
                app()->instance(self::class, $previous);
            } else {
                app()->forgetInstance(self::class);
            }
        }
    }

    /**
     * Container'a bağla (middleware / job başı).
     */
    public static function bind(int $companyId): self
    {
        $ctx = new self($companyId);
        app()->instance(self::class, $ctx);

        return $ctx;
    }

    public static function forget(): void
    {
        if (self::isBound()) {
            app()->forgetInstance(self::class);
        }
    }
}
