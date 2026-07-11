<?php

namespace App\Traits;

use App\Observers\AuditObserver;

/**
 * Model create/update/delete için otomatik ActivityLog (Faz 2 Audit v2).
 *
 * Kullanım:
 *   use Auditable;
 *   protected array $auditMasked = ['gross_salary', 'national_id'];
 *   protected array $auditIgnore = []; // ek ignore
 */
trait Auditable
{
    public static bool $auditingEnabled = true;

    protected static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    public function isAuditingEnabled(): bool
    {
        return static::$auditingEnabled;
    }

    /**
     * @return list<string>
     */
    public function getAuditIgnoreFields(): array
    {
        $defaults = [
            'created_at',
            'updated_at',
            'deleted_at',
            'remember_token',
            'created_by',
            'updated_by',
        ];

        $extra = property_exists($this, 'auditIgnore') && is_array($this->auditIgnore)
            ? $this->auditIgnore
            : [];

        return array_values(array_unique(array_merge($defaults, $extra)));
    }

    /**
     * @return list<string>
     */
    public function getAuditMaskedFields(): array
    {
        if (property_exists($this, 'auditMasked') && is_array($this->auditMasked)) {
            return array_values($this->auditMasked);
        }

        return [];
    }

    /**
     * Diff açıklamasında kullanılacak etiketler (opsiyonel).
     *
     * @return array<string, string>
     */
    public function getAuditMaskedFieldLabels(): array
    {
        if (property_exists($this, 'auditMaskedLabels') && is_array($this->auditMaskedLabels)) {
            return $this->auditMaskedLabels;
        }

        return [];
    }

    /**
     * Geçici olarak audit kapat (seed/import vb.).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public static function withoutAuditing(callable $callback): mixed
    {
        $previous = static::$auditingEnabled;
        static::$auditingEnabled = false;

        try {
            return $callback();
        } finally {
            static::$auditingEnabled = $previous;
        }
    }
}
