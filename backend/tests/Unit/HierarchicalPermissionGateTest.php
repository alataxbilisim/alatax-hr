<?php

namespace Tests\Unit;

use App\Enums\UserType;
use App\Models\User;
use App\Support\HierarchicalPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HierarchicalPermissionGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_matcher_exact_and_wildcards(): void
    {
        $this->assertTrue(HierarchicalPermission::matches(
            ['employees.list.view'],
            'employees.list.view'
        ));
        $this->assertTrue(HierarchicalPermission::matches(
            ['employees.list.*'],
            'employees.list.view'
        ));
        $this->assertTrue(HierarchicalPermission::matches(
            ['employees.*'],
            'employees.list.view'
        ));
        $this->assertTrue(HierarchicalPermission::matches(
            ['*'],
            'employees.list.view'
        ));
        $this->assertFalse(HierarchicalPermission::matches(
            ['employees.list.create'],
            'employees.list.view'
        ));
        $this->assertFalse(HierarchicalPermission::matches(
            ['management.*'],
            'employees.list.view'
        ));
    }

    public function test_super_admin_bypasses_gate(): void
    {
        $user = User::factory()->superAdmin()->create();

        $this->assertTrue(Gate::forUser($user)->allows('management.audit_logs.view'));
        $this->assertTrue(Gate::forUser($user)->allows('timesheet.attendance.approve'));
    }

    public function test_company_admin_bypasses_gate_temporarily(): void
    {
        $user = User::factory()->companyAdmin()->create();

        $this->assertTrue(Gate::forUser($user)->allows('management.audit_logs.view'));
        $this->assertTrue(Gate::forUser($user)->allows('timesheet.attendance.view'));
    }

    public function test_regular_user_with_wildcard_permission_allowed(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::findOrCreate('management.*', 'sanctum');
        Permission::findOrCreate('management.audit_logs.view', 'sanctum');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->regularUser()->create();
        $user->givePermissionTo('management.*');

        $this->assertTrue(Gate::forUser($user)->allows('management.audit_logs.view'));
        $this->assertTrue(Gate::forUser($user)->allows('management.audit_logs.export'));
    }

    public function test_regular_user_without_permission_denied(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::findOrCreate('management.audit_logs.view', 'sanctum');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->regularUser()->create();

        $this->assertFalse(Gate::forUser($user)->allows('management.audit_logs.view'));
        $this->assertFalse(Gate::forUser($user)->allows('timesheet.attendance.view'));
    }

    public function test_regular_user_with_exact_permission_allowed(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::findOrCreate('timesheet.attendance.view', 'sanctum');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $user = User::factory()->regularUser()->create();
        $user->givePermissionTo('timesheet.attendance.view');

        $this->assertTrue(Gate::forUser($user)->allows('timesheet.attendance.view'));
        $this->assertFalse(Gate::forUser($user)->allows('timesheet.attendance.approve'));
        $this->assertSame(UserType::User, $user->type);
    }
}
