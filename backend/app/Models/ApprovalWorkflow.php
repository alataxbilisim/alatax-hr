<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApprovalWorkflow extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'entity_type',
        'description',
        'is_active',
        'is_default',
        'conditions',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
        'conditions' => 'array',
    ];

    // Desteklenen entity tipleri
    const ENTITY_LEAVE_REQUEST = 'leave_request';

    const ENTITY_ASSET_REQUEST = 'asset_request';

    const ENTITY_EXPENSE_REQUEST = 'expense_request';

    const ENTITY_TRAINING_REQUEST = 'training_request';

    const ENTITY_DOCUMENT_APPROVAL = 'document_approval';

    public static function getEntityTypes(): array
    {
        return [
            self::ENTITY_LEAVE_REQUEST => 'İzin Talebi',
            self::ENTITY_ASSET_REQUEST => 'Varlık Talebi',
            self::ENTITY_EXPENSE_REQUEST => 'Masraf Talebi',
            self::ENTITY_TRAINING_REQUEST => 'Eğitim Talebi',
            self::ENTITY_DOCUMENT_APPROVAL => 'Evrak Onayı',
        ];
    }

    // Relationships
    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class)->orderBy('step_order');
    }

    public function records(): HasMany
    {
        return $this->hasMany(ApprovalRecord::class);
    }

    public function leaveTypes(): HasMany
    {
        return $this->hasMany(LeaveType::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForEntity($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Methods
    public function getFirstStep(): ?ApprovalStep
    {
        return $this->steps()->orderBy('step_order')->first();
    }

    public function getNextStep(int $currentOrder): ?ApprovalStep
    {
        return $this->steps()
            ->where('step_order', '>', $currentOrder)
            ->orderBy('step_order')
            ->first();
    }

    public function getTotalSteps(): int
    {
        return $this->steps()->count();
    }

    /**
     * Koşullara göre uygun workflow'u bul
     */
    public static function findForRequest(int $companyId, string $entityType, array $context = []): ?self
    {
        // Önce koşullu workflow'ları kontrol et
        $workflows = self::where('company_id', $companyId)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->whereNotNull('conditions')
            ->get();

        foreach ($workflows as $workflow) {
            if ($workflow->matchesConditions($context)) {
                return $workflow;
            }
        }

        // Koşul eşleşmezse varsayılanı dön
        return self::where('company_id', $companyId)
            ->where('entity_type', $entityType)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();
    }

    /**
     * Koşulları kontrol et
     */
    public function matchesConditions(array $context): bool
    {
        if (empty($this->conditions)) {
            return true;
        }

        foreach ($this->conditions as $condition) {
            $field = $condition['field'] ?? null;
            $operator = $condition['operator'] ?? '=';
            $value = $condition['value'] ?? null;
            $contextValue = $context[$field] ?? null;

            if ($field === null || $contextValue === null) {
                continue;
            }

            switch ($operator) {
                case '=':
                case '==':
                    if ($contextValue != $value) {
                        return false;
                    }
                    break;
                case '>':
                    if ($contextValue <= $value) {
                        return false;
                    }
                    break;
                case '>=':
                    if ($contextValue < $value) {
                        return false;
                    }
                    break;
                case '<':
                    if ($contextValue >= $value) {
                        return false;
                    }
                    break;
                case '<=':
                    if ($contextValue > $value) {
                        return false;
                    }
                    break;
                case 'in':
                    if (! in_array($contextValue, (array) $value)) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }
}
