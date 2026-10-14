<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RoleDefaultDashboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'role_key',
        'dashboard_system_key',
    ];
}
