<?php

namespace App\Services\Settings;

use App\Models\User;

/**
 * Tek satırlık okuma API'si — modüller doğrudan tabloya gitmez.
 *
 * Örnek: Settings::get('leaves.retention.months', ['company_id' => $id])
 */
final class Settings
{
    /**
     * @param  array{company_id?: int|null, user_id?: int|null, department_id?: int|null, branch_id?: int|null}  $scope
     */
    public static function get(string $key, array $scope = []): mixed
    {
        return app(SettingsResolver::class)->get($key, $scope);
    }

    /**
     * @return array{company_id?: int|null, user_id?: int|null, department_id?: int|null, branch_id?: int|null}
     */
    public static function scopeFromUser(?User $user): array
    {
        return app(SettingsResolver::class)->scopeFromUser($user);
    }
}
