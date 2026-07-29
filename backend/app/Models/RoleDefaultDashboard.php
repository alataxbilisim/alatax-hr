<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RoleDefaultDashboard extends Model
{
    protected $fillable = [
        'role_key',
        'dashboard_system_key',
    ];
}
