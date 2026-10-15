<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Models\Company;
use App\Models\Employee;
use Database\Seeders\DemoDataSeeder;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * Adım 03B S4 — demo:fill-employee-names yalnız demo şirketindeki boş adları doldurur, idempotent.
 */
class DemoFillEmployeeNamesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_fills_only_empty_demo_names_and_is_idempotent(): void
    {
        $demo = Company::factory()->create(['slug' => DemoDataSeeder::COMPANY_SLUG, 'status' => CompanyStatus::Active]);
        $other = Company::factory()->create(['status' => CompanyStatus::Active]);

        $empty = Employee::factory()->create(['company_id' => $demo->id, 'employee_code' => 'DEM-035', 'full_name' => null, 'user_id' => null]);
        $named = Employee::factory()->create(['company_id' => $demo->id, 'employee_code' => 'DEM-036', 'full_name' => 'Elle Girilmiş Ad']);
        $foreign = Employee::factory()->create(['company_id' => $other->id, 'employee_code' => 'DEM-037', 'full_name' => '']);

        $this->artisan('demo:fill-employee-names', ['--dry-run' => true])->assertSuccessful();
        $this->assertNull($empty->fresh()->full_name);

        $this->artisan('demo:fill-employee-names')->expectsOutputToContain('Kayıt: 1')->assertSuccessful();

        $this->assertSame(DemoDataSeeder::employeeFullName(35), $empty->fresh()->full_name);
        $this->assertSame('Nilüfer Ergin', $empty->fresh()->full_name);
        $this->assertSame('Elle Girilmiş Ad', $named->fresh()->full_name);
        $this->assertSame('', $foreign->fresh()->full_name);

        $this->artisan('demo:fill-employee-names')->expectsOutputToContain('Kayıt: 0')->assertSuccessful();
    }
}
