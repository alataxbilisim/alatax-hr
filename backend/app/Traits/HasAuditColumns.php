<?php

namespace App\Traits;

/**
 * Audit kolonları için trait
 * created_by, updated_by gibi kolonları otomatik yönetir
 */
trait HasAuditColumns
{
    /**
     * Boot method
     */
    protected static function bootHasAuditColumns(): void
    {
        // Kayıt oluşturulurken
        static::creating(function ($model) {
            if (auth()->check()) {
                if ($model->isFillable('created_by') && ! $model->created_by) {
                    $model->created_by = auth()->id();
                }
                if ($model->isFillable('updated_by')) {
                    $model->updated_by = auth()->id();
                }
            }
        });

        // Kayıt güncellenirken
        static::updating(function ($model) {
            if (auth()->check() && $model->isFillable('updated_by')) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /**
     * Oluşturan kullanıcı ilişkisi
     */
    public function createdBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Güncelleyen kullanıcı ilişkisi
     */
    public function updatedBy()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
