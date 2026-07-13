<?php

namespace Tests\Feature\Api;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimesheetTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'status' => CompanyStatus::Active,
        ]);
        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($this->user)->create();
    }

    /** @test */
    public function user_can_get_today_status(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/portal/timesheet/today');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'date',
                    'is_clocked_in',
                    'is_clocked_out',
                    'is_on_break',
                ],
            ]);
    }

    /** @test */
    public function user_can_clock_in(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/portal/timesheet/clock-in', [
            'latitude' => 41.0082,
            'longitude' => 28.9784,
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
            ]);

        $this->assertTrue(
            AttendanceRecord::query()
                ->where('user_id', $this->user->id)
                ->where('company_id', $this->company->id)
                ->whereDate('date', now()->toDateString())
                ->where('status', 'present')
                ->exists()
        );
    }

    /** @test */
    public function user_cannot_clock_in_twice(): void
    {
        Sanctum::actingAs($this->user);

        // First clock in
        $this->postJson('/api/v1/portal/timesheet/clock-in');

        // Second attempt
        $response = $this->postJson('/api/v1/portal/timesheet/clock-in');

        $response->assertStatus(422);
    }

    /** @test */
    public function user_can_clock_out_after_clocking_in(): void
    {
        Sanctum::actingAs($this->user);

        // Clock in first
        $this->postJson('/api/v1/portal/timesheet/clock-in');

        // Clock out
        $response = $this->postJson('/api/v1/portal/timesheet/clock-out');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'clock_in',
                    'clock_out',
                    'total_hours',
                ],
            ]);
    }

    /** @test */
    public function user_cannot_clock_out_without_clocking_in(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/portal/timesheet/clock-out');

        $response->assertStatus(422);
    }

    /** @test */
    public function user_can_start_break(): void
    {
        Sanctum::actingAs($this->user);

        // Clock in first
        $this->postJson('/api/v1/portal/timesheet/clock-in');

        // Start break
        $response = $this->postJson('/api/v1/portal/timesheet/break/start');

        $response->assertOk();
    }

    /** @test */
    public function user_can_end_break(): void
    {
        Sanctum::actingAs($this->user);

        // Clock in
        $this->postJson('/api/v1/portal/timesheet/clock-in');
        // Start break
        $this->postJson('/api/v1/portal/timesheet/break/start');

        // End break
        $response = $this->postJson('/api/v1/portal/timesheet/break/end');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'break_start',
                    'break_end',
                ],
            ]);
    }

    /** @test */
    public function user_can_get_weekly_records(): void
    {
        Sanctum::actingAs($this->user);

        // Create some attendance records
        AttendanceRecord::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'date' => now()->startOfWeek(),
            'clock_in' => '09:00',
            'clock_out' => '18:00',
            'total_hours' => 8,
            'status' => 'present',
        ]);

        $response = $this->getJson('/api/v1/portal/timesheet/weekly');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'week_start',
                    'week_end',
                    'total_hours',
                    'working_days',
                    'records',
                ],
            ]);
    }

    /** @test */
    public function user_can_get_monthly_records(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/v1/portal/timesheet/monthly?year='.now()->year.'&month='.now()->month);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'year',
                    'month',
                    'month_name',
                    'total_hours',
                    'working_days',
                    'records',
                ],
            ]);
    }
}
