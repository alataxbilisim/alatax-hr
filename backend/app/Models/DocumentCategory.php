<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DocumentCategory extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'icon',
        'color',
        'description',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'category_id');
    }
}

