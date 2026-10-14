<?php

namespace Tests\Concerns;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Rol/izin ile kullanıcı oluşturma. İzinler `sanctum` guard'ında yoksa oluşturulur.
 */
trait CreatesUsersWithPermissions
{
    protected function createActiveCompany(array $attributes = []): Company
    {
        return Company::factory()->create(array_merge(['status' => CompanyStatus::Active], $attributes));
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function ensurePermissions(array $permissions): void
    {
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'sanctum');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Firma kullanıcısı (UserType::User) + verilen izinler.
     *
     * @param  list<string>  $permissions
     */
    protected function userWithPermissions(Company $company, array $permissions = [], array $attributes = []): User
    {
        $this->ensurePermissions($permissions);

        $user = User::factory()->create(array_merge([
            'home_company_id' => $company->id,
            'type' => UserType::User,
            'is_active' => true,
        ], $attributes));

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /**
     * Firma yöneticisi: Spatie `admin` rolü + mevcut tüm sanctum izinleri.
     *
     * @param  list<string>  $extraPermissions  rol senkronundan önce oluşturulacak izinler
     */
    protected function companyAdminFor(Company $company, array $extraPermissions = []): User
    {
        $this->ensurePermissions($extraPermissions);

        $user = User::factory()->companyAdmin($company)->create(['is_active' => true]);

        return $this->assignSpatieAdminRole($user);
    }

    protected function superAdminUser(): User
    {
        return User::factory()->superAdmin()->create(['is_active' => true]);
    }
}
