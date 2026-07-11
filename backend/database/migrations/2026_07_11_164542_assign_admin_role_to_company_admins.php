<?php

use App\Enums\UserType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mevcut company_admin kullanıcılara Spatie admin (sanctum) rolü ata (idempotent).
 * Gate company_admin bypass kaldırmadan önce çalışmalı.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles') || ! Schema::hasTable('model_has_roles')) {
            return;
        }

        $adminRole = Role::query()->firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum']
        );

        if (Schema::hasColumn('roles', 'data_scope') && $adminRole->data_scope === null) {
            $adminRole->forceFill(['data_scope' => 'company'])->save();
        }

        $userIds = User::query()
            ->where('type', UserType::CompanyAdmin->value)
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'admin')->where('roles.guard_name', 'sanctum');
            })
            ->pluck('id');

        $now = now();
        foreach ($userIds as $userId) {
            $exists = DB::table('model_has_roles')
                ->where('role_id', $adminRole->id)
                ->where('model_type', User::class)
                ->where('model_id', $userId)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('model_has_roles')->insert([
                'role_id' => $adminRole->id,
                'model_type' => User::class,
                'model_id' => $userId,
            ]);
        }
    }

    public function down(): void
    {
        // Rol atamasını geri almak istenmez — no-op
    }
};
