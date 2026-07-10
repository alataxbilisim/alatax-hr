<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use App\Traits\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ApplicationForm extends Model
{
    use BelongsToCompany, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'description',
        'fields',
        'is_default',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fields' => 'array',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    // Relationships
    public function jobPositions()
    {
        return $this->hasMany(JobPosition::class, 'form_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    // Default fields for a new form
    public static function getDefaultFields(): array
    {
        return [
            [
                'id' => 'cover_letter',
                'type' => 'textarea',
                'label' => 'Ön Yazı',
                'placeholder' => 'Kendinizi tanıtın ve neden bu pozisyona uygun olduğunuzu açıklayın',
                'required' => false,
                'order' => 1,
            ],
            [
                'id' => 'expected_salary',
                'type' => 'number',
                'label' => 'Beklenen Maaş',
                'placeholder' => 'Aylık beklentinizi TL olarak belirtin',
                'required' => false,
                'order' => 2,
            ],
            [
                'id' => 'start_date',
                'type' => 'date',
                'label' => 'Ne zaman işe başlayabilirsiniz?',
                'required' => false,
                'order' => 3,
            ],
            [
                'id' => 'referral_source',
                'type' => 'select',
                'label' => 'Bu ilanı nereden duydunuz?',
                'options' => [
                    'website' => 'Şirket Web Sitesi',
                    'linkedin' => 'LinkedIn',
                    'kariyer_net' => 'Kariyer.net',
                    'indeed' => 'Indeed',
                    'referral' => 'Çalışan Referansı',
                    'other' => 'Diğer',
                ],
                'required' => false,
                'order' => 4,
            ],
        ];
    }
}
