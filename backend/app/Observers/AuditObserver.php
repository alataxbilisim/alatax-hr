<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditable modeller için otomatik create/update/delete audit.
 */
class AuditObserver
{
    public function created(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $newValues = $this->filterAndMask($model, $model->getAttributes());

        ActivityLog::log(
            'create',
            $model,
            class_basename($model).' oluşturuldu',
            null,
            $newValues !== [] ? $newValues : null
        );
    }

    public function updated(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $changes = $model->getChanges();
        $ignore = $this->ignoreFields($model);

        $oldValues = [];
        $newValues = [];

        foreach ($changes as $key => $newValue) {
            if (in_array($key, $ignore, true)) {
                continue;
            }

            $oldValues[$key] = $model->getOriginal($key);
            $newValues[$key] = $newValue;
        }

        if ($oldValues === [] && $newValues === []) {
            return;
        }

        [$oldValues, $newValues] = $this->maskDiff($model, $oldValues, $newValues);

        ActivityLog::log(
            'update',
            $model,
            $this->updateDescription($model, $oldValues, $newValues),
            $oldValues,
            $newValues
        );
    }

    public function deleted(Model $model): void
    {
        if ($this->shouldSkip($model)) {
            return;
        }

        $oldValues = $this->filterAndMask($model, $model->getAttributes());

        ActivityLog::log(
            'delete',
            $model,
            class_basename($model).' silindi',
            $oldValues !== [] ? $oldValues : null,
            null
        );
    }

    protected function shouldSkip(Model $model): bool
    {
        if (! method_exists($model, 'isAuditingEnabled')) {
            return true;
        }

        return ! $model->isAuditingEnabled();
    }

    /**
     * @return list<string>
     */
    protected function ignoreFields(Model $model): array
    {
        if (method_exists($model, 'getAuditIgnoreFields')) {
            return $model->getAuditIgnoreFields();
        }

        return ['created_at', 'updated_at', 'deleted_at', 'remember_token', 'created_by', 'updated_by'];
    }

    /**
     * @return list<string>
     */
    protected function maskedFields(Model $model): array
    {
        if (method_exists($model, 'getAuditMaskedFields')) {
            return $model->getAuditMaskedFields();
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function filterAndMask(Model $model, array $attributes): array
    {
        $ignore = $this->ignoreFields($model);
        $masked = $this->maskedFields($model);
        $result = [];

        foreach ($attributes as $key => $value) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            if (in_array($key, $masked, true) && $value !== null) {
                $result[$key] = '*** güncellendi';

                continue;
            }
            $result[$key] = $value;
        }

        if (method_exists($model, 'transformAuditAttributes')) {
            $result = $model->transformAuditAttributes($result);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    protected function maskDiff(Model $model, array $oldValues, array $newValues): array
    {
        $masked = $this->maskedFields($model);

        foreach ($masked as $field) {
            if (array_key_exists($field, $oldValues) || array_key_exists($field, $newValues)) {
                if (array_key_exists($field, $oldValues) && $oldValues[$field] !== null) {
                    $oldValues[$field] = '*** güncellendi';
                }
                if (array_key_exists($field, $newValues) && $newValues[$field] !== null) {
                    $newValues[$field] = '*** güncellendi';
                }
            }
        }

        if (method_exists($model, 'transformAuditAttributes')) {
            $oldValues = $model->transformAuditAttributes($oldValues);
            $newValues = $model->transformAuditAttributes($newValues);
        }

        return [$oldValues, $newValues];
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    protected function updateDescription(Model $model, array $oldValues, array $newValues): string
    {
        $description = class_basename($model).' güncellendi';
        $masked = $this->maskedFields($model);
        $labels = method_exists($model, 'getAuditMaskedFieldLabels')
            ? $model->getAuditMaskedFieldLabels()
            : [];

        $notes = [];
        foreach ($masked as $field) {
            if (! array_key_exists($field, $newValues) && ! array_key_exists($field, $oldValues)) {
                continue;
            }
            $label = $labels[$field] ?? $field;
            $notes[] = "{$label} güncellendi";
        }

        if ($notes !== []) {
            $description .= ' ('.implode(', ', array_unique($notes)).')';
        }

        return $description;
    }
}
