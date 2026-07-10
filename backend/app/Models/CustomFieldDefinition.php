<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomFieldDefinition extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'entity_type',
        'field_key',
        'field_label',
        'field_type',
        'field_options',
        'is_required',
        'is_active',
        'sort_order',
        'validation_rules',
        'placeholder',
        'help_text',
        'default_value',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'field_options' => 'array',
        'validation_rules' => 'array',
        'is_required' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    // Field types
    const TYPE_TEXT = 'text';

    const TYPE_NUMBER = 'number';

    const TYPE_DATE = 'date';

    const TYPE_SELECT = 'select';

    const TYPE_CHECKBOX = 'checkbox';

    const TYPE_RADIO = 'radio';

    const TYPE_TEXTAREA = 'textarea';

    const TYPE_FILE = 'file';

    const TYPE_EMAIL = 'email';

    const TYPE_PHONE = 'phone';

    const TYPE_URL = 'url';

    // Entity types
    const ENTITY_EMPLOYEE = 'employee';

    const ENTITY_LEAVE_REQUEST = 'leave_request';

    const ENTITY_TRAINING = 'training';

    const ENTITY_PERFORMANCE = 'performance';

    const ENTITY_DOCUMENT = 'document';

    const ENTITY_EXPENSE = 'expense';

    const ENTITY_JOB_APPLICATION = 'job_application';

    const ENTITY_ASSET = 'asset';

    /**
     * Get available field types
     */
    public static function getFieldTypes(): array
    {
        return [
            self::TYPE_TEXT => 'Metin',
            self::TYPE_NUMBER => 'Sayı',
            self::TYPE_DATE => 'Tarih',
            self::TYPE_SELECT => 'Açılır Liste',
            self::TYPE_CHECKBOX => 'Onay Kutusu',
            self::TYPE_RADIO => 'Seçenek Butonu',
            self::TYPE_TEXTAREA => 'Uzun Metin',
            self::TYPE_FILE => 'Dosya',
            self::TYPE_EMAIL => 'E-posta',
            self::TYPE_PHONE => 'Telefon',
            self::TYPE_URL => 'Web Adresi',
        ];
    }

    /**
     * Get available entity types
     */
    public static function getEntityTypes(): array
    {
        return [
            self::ENTITY_EMPLOYEE => 'Personel',
            self::ENTITY_LEAVE_REQUEST => 'İzin Talebi',
            self::ENTITY_TRAINING => 'Eğitim',
            self::ENTITY_PERFORMANCE => 'Performans',
            self::ENTITY_DOCUMENT => 'Belge',
            self::ENTITY_EXPENSE => 'Masraf',
            self::ENTITY_JOB_APPLICATION => 'İş Başvurusu',
            self::ENTITY_ASSET => 'Varlık',
        ];
    }

    /**
     * Scope: Filter by entity type
     */
    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    /**
     * Scope: Only active fields
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Ordered by sort_order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('field_label');
    }

    /**
     * Validate a value against this field's rules
     */
    public function validateValue($value): bool
    {
        if ($this->is_required && empty($value)) {
            return false;
        }

        if (! empty($this->validation_rules)) {
            // TODO: Implement validation logic
        }

        return true;
    }
}
