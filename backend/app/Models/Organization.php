<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organization $org): void {
            if (empty($org->slug)) {
                $org->slug = Str::slug($org->name);
                $original = $org->slug;
                $n = 1;
                while (static::where('slug', $org->slug)->exists()) {
                    $org->slug = $original.'-'.$n++;
                }
            }
        });
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class);
    }
}
