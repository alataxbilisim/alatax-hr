<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationStatusLog extends Model
{
    protected $fillable = [
        'job_application_id',
        'from_status',
        'to_status',
        'note',
        'changed_by',
    ];

    // Relationships
    public function application()
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

