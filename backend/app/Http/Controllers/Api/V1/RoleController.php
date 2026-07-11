<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ActivityLog;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class RoleController extends BaseController
{
    /**
     * Rol listesi
     */
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')
            ->where('guard_name', 'sanctum')
            ->get()
            ->map(function ($role) {
                // model_has_roles tablosundan kullanıcı sayısını al
                $usersCount = \DB::table('model_has_roles')
                    ->where('role_id', $role->id)
                    ->where('model_type', \App\Models\User::class)
                    ->count();

                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                    'permissions' => $role->permissions->map(function ($perm) {
                        return [
                            'id' => $perm->id,
                            'name' => $perm->name,
                        ];
                    }),
                    'users_count' => $usersCount,
                    'created_at' => $role->created_at->toIso8601String(),
                ];
            });

        return $this->success($roles, 'Roller listelendi');
    }

    /**
     * Rol detay
     */
    public function show(Role $role): JsonResponse
    {
        // Role'a sahip kullanıcıları al
        $userIds = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', \App\Models\User::class)
            ->pluck('model_id');

        $users = \App\Models\User::whereIn('id', $userIds)
            ->select('id', 'name', 'email')
            ->get();

        return $this->success([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ];
            }),
            'users' => $users,
            'created_at' => $role->created_at->toIso8601String(),
        ]);
    }

    /**
     * Rol oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'permissions' => 'required|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'guard_name' => 'sanctum',
        ]);

        // Pivot — observer yakalamaz; özel izin logu
        $role->syncPermissions($validated['permissions']);
        ActivityLog::log(
            'permission_sync',
            $role,
            'Rol izinleri atandı: '.$role->name,
            null,
            ['permissions' => $validated['permissions']]
        );

        return $this->created([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ];
            }),
            'created_at' => $role->created_at->toIso8601String(),
        ], 'Rol oluşturuldu');
    }

    /**
     * Rol güncelle
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        // Varsayılan rolleri düzenleyemez
        $protectedRoles = ['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'];
        if (in_array($role->name, $protectedRoles)) {
            return $this->error('Varsayılan roller düzenlenemez', 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:50|unique:roles,name,'.$role->id,
            'permissions' => 'sometimes|array|min:1',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if (isset($validated['name'])) {
            $role->update(['name' => $validated['name']]);
        }

        if (isset($validated['permissions'])) {
            $oldPermissions = $role->permissions->pluck('name')->sort()->values()->all();
            $role->syncPermissions($validated['permissions']);
            $newPermissions = $role->permissions->pluck('name')->sort()->values()->all();

            if ($oldPermissions !== $newPermissions) {
                ActivityLog::log(
                    'permission_sync',
                    $role,
                    'Rol izinleri güncellendi: '.$role->name,
                    ['permissions' => $oldPermissions],
                    ['permissions' => $newPermissions]
                );
            }
        }

        return $this->success([
            'id' => $role->id,
            'name' => $role->name,
            'guard_name' => $role->guard_name,
            'permissions' => $role->permissions->map(function ($perm) {
                return [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ];
            }),
            'created_at' => $role->created_at->toIso8601String(),
        ], 'Rol güncellendi');
    }

    /**
     * Rol sil
     */
    public function destroy(Role $role): JsonResponse
    {
        // Varsayılan rolleri silemez
        $protectedRoles = ['admin', 'hr_manager', 'hr_specialist', 'manager', 'employee'];
        if (in_array($role->name, $protectedRoles)) {
            return $this->error('Varsayılan roller silinemez', 403);
        }

        // Kullanıcısı olan rol silinemez (model_has_roles tablosundan kontrol et)
        $usersCount = \DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->where('model_type', \App\Models\User::class)
            ->count();

        if ($usersCount > 0) {
            return $this->error('Bu role sahip kullanıcılar var. Önce kullanıcıların rollerini değiştirin.', 400);
        }

        // Observer delete log yazar
        $role->delete();

        return $this->success(null, 'Rol silindi');
    }

    /**
     * Tüm yetkiler
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::where('guard_name', 'sanctum')
            ->get()
            ->map(function ($permission) {
                return [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ];
            });

        return $this->success($permissions, 'Yetkiler listelendi');
    }
}
