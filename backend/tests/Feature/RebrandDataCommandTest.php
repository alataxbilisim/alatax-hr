<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

class RebrandDataCommandTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $admin;

    private User $conflicting;

    private int $logId;

    protected function setUp(): void
    {
        parent::setUp();

        // brand-guard:allow-start
        $this->company = Company::factory()->create([
            'name' => 'ALATAX HR Demo A.Ş.',
            'slug' => 'alatax-demo-as',
            'email' => 'info@alataxbilisim.com',
            'website' => 'https://github.com/alataxbilisim/alatax-hr',
            'settings' => ['brand' => 'Alatax', 'support' => 'destek@alatax.local'],
        ]);
        $this->admin = User::factory()->create(['email' => 'admin@alataxbilisim.com']);
        $this->conflicting = User::factory()->create(['email' => 'ik@alatax.local']);
        User::factory()->create(['email' => 'ik@dobedan.local']);

        $this->logId = DB::table('activity_logs')->insertGetId([
            'action' => 'updated',
            'description' => 'alatax-demo-as güncellendi',
            'is_successful' => true,
            'created_at' => now(),
        ]);
        // brand-guard:allow-end
    }

    public function test_dry_run_changes_nothing(): void
    {
        $before = $this->snapshot();

        $this->artisan('dobedan:rebrand-data', ['--dry-run' => true])
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertSame($before, $this->snapshot());
    }

    public function test_real_run_converts_brand_values_and_skips_conflicts(): void
    {
        $this->artisan('dobedan:rebrand-data')
            ->expectsOutputToContain('unique_conflict')
            ->assertSuccessful();

        $company = $this->company->fresh();
        $this->assertSame('Dobedan HR Demo A.Ş.', $company->name);
        $this->assertSame('dobedan-demo-as', $company->slug);
        $this->assertSame('info@dobedan.com', $company->email);
        $this->assertSame(['brand' => 'Dobedan', 'support' => 'destek@dobedan.local'], $company->settings);
        $this->assertSame('admin@dobedan.com', $this->admin->fresh()->email);

        // brand-guard:allow-start
        $this->assertSame('https://github.com/alataxbilisim/alatax-hr', $company->website);
        $this->assertSame('ik@alatax.local', $this->conflicting->fresh()->email);
        $this->assertSame('alatax-demo-as güncellendi', DB::table('activity_logs')->where('id', $this->logId)->value('description'));
        // brand-guard:allow-end
    }

    public function test_second_run_is_idempotent(): void
    {
        Artisan::call('dobedan:rebrand-data');
        $after = $this->snapshot();

        $this->artisan('dobedan:rebrand-data')
            ->expectsOutputToContain('Değişecek satır yok.')
            ->assertSuccessful();

        $this->assertSame($after, $this->snapshot());
    }

    /** @return array<string, mixed> */
    private function snapshot(): array
    {
        return [
            'companies' => DB::table('companies')->orderBy('id')
                ->get(['id', 'name', 'slug', 'email', 'website', 'settings'])
                ->map(fn ($row) => (array) $row)
                ->all(),
            'users' => DB::table('users')->orderBy('id')->pluck('email', 'id')->all(),
            'logs' => DB::table('activity_logs')->orderBy('id')->pluck('description', 'id')->all(),
        ];
    }
}
