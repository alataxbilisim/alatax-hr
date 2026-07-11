<?php

namespace App\Models;

use App\Traits\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Spatie Role + Auditable (Faz 2 Audit v2).
 * İzin pivot değişiklikleri observer'a düşmez — RoleController'da özel log.
 */
class Role extends SpatieRole
{
    use Auditable;

    /** @var list<string> */
    protected array $auditMasked = [];
}
