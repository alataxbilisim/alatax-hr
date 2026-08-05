<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\Company;
use App\Models\Dashboard;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur8 — sistem panosu paylaşımlı satır; layout salt okunur; kopya bağımsız.
 */
class SystemDashboardReadonlyTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_users_cannot_mutate_system_layout_clone_is_independent(): void
    {
        $this->seed(PermissionSeeder::class);
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $company = Company::factory()->create(['status' => CompanyStatus::Active]);

        $userA = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $userB = User::factory()->create([
            'company_id' => $company->id,
            'type' => UserType::CompanyAdmin,
        ]);
        $this->assignSpatieAdminRole($userA->fresh());
        $this->assignSpatieAdminRole($userB->fresh());

        $system = Dashboard::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'owner_id' => $userA->id,
            'created_by' => $userA->id,
            'name' => 'Sistem HR',
            'is_system' => true,
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'sys1',
                        'type' => 'text',
                        'title' => 'Orijinal',
                        'content' => 'shared',
                        'layout' => ['x' => 0, 'y' => 0, 'w' => 6, 'h' => 4],
                    ],
                ],
            ],
        ]);

        Sanctum::actingAs($userA->fresh());
        $this->putJson('/api/v1/dashboards/'.$system->id, [
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'sys1',
                        'type' => 'text',
                        'title' => 'A değiştirdi',
                        'content' => 'hack',
                        'layout' => ['x' => 3, 'y' => 0, 'w' => 6, 'h' => 4],
                    ],
                ],
            ],
        ])->assertForbidden();

        Sanctum::actingAs($userB->fresh());
        $this->putJson('/api/v1/dashboards/'.$system->id, [
            'layout' => ['widgets' => []],
        ])->assertForbidden();

        $system->refresh();
        $this->assertSame('Orijinal', $system->widgets()[0]['title'] ?? null);

        Sanctum::actingAs($userA->fresh());
        $copyA = $this->postJson('/api/v1/dashboards/'.$system->id.'/clone', [
            'name' => 'Kopya A',
        ])->assertCreated()->json('data');

        Sanctum::actingAs($userB->fresh());
        $copyB = $this->postJson('/api/v1/dashboards/'.$system->id.'/clone', [
            'name' => 'Kopya B',
        ])->assertCreated()->json('data');

        $this->assertFalse((bool) ($copyA['is_system'] ?? true));
        $this->assertFalse((bool) ($copyB['is_system'] ?? true));
        $this->assertNotSame($copyA['id'], $copyB['id']);

        Sanctum::actingAs($userA->fresh());
        $this->putJson('/api/v1/dashboards/'.$copyA['id'], [
            'layout' => [
                'widgets' => [
                    [
                        'id' => 'sys1',
                        'type' => 'text',
                        'title' => 'A kopyası',
                        'content' => 'a',
                        'layout' => ['x' => 1, 'y' => 0, 'w' => 6, 'h' => 4],
                    ],
                ],
            ],
        ])->assertOk();

        $freshB = Dashboard::query()->find($copyB['id']);
        $this->assertSame('Orijinal', $freshB->widgets()[0]['title'] ?? null);

        $system->refresh();
        $this->assertSame('Orijinal', $system->widgets()[0]['title'] ?? null);
    }
}
