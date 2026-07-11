<?php

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * UserType::company_admin olup Spatie 'admin' rolü olmayan kullanıcılara rol ata.
 * Gate company_admin bypass kaldırmadan ÖNCE çalıştırılmalı (idempotent).
 */
class EnsureCompanyAdminHasAdminRole extends Command
{
    protected $signature = 'users:ensure-admin-role
                            {--dry-run : Sadece say, yazma}';

    protected $description = 'company_admin type kullanıcılarına Spatie admin (sanctum) rolü garantile';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $adminRole = Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum']
        );

        if ($adminRole->data_scope === null) {
            $adminRole->data_scope = 'company';
            $adminRole->save();
        }

        $query = User::query()
            ->where('type', UserType::CompanyAdmin)
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'admin')->where('guard_name', 'sanctum');
            });

        $count = $query->count();
        $this->info("admin rolü olmayan company_admin sayısı: {$count}");

        if ($dryRun || $count === 0) {
            return self::SUCCESS;
        }

        $assigned = 0;
        $query->orderBy('id')->chunkById(100, function ($users) use ($adminRole, &$assigned) {
            foreach ($users as $user) {
                $user->assignRole($adminRole);
                $assigned++;
            }
        });

        $this->info("Atanan: {$assigned}");

        return self::SUCCESS;
    }
}
