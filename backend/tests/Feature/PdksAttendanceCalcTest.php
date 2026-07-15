<?php

namespace Tests\Feature;

use App\Enums\CompanyStatus;
use App\Enums\UserType;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Shift;
use App\Models\User;
use App\Services\Timesheet\AttendanceCalcService;
use App\Services\Timesheet\AttendanceClockService;
use Illuminate\Support\Carbon;
use Tests\Concerns\RefreshDatabase;
use Tests\TestCase;

/**
 * PDKS-2 Z2: geç/erken/mesai hesap motoru.
 */
class PdksAttendanceCalcTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $user;

    private AttendanceCalcService $calc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create([
            'status' => CompanyStatus::Active,
            'settings' => [
                'general' => [
                    'default_work_start' => '09:00',
                    'default_work_end' => '18:00',
                    'late_tolerance_minutes' => 15,
                ],
            ],
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
            'type' => UserType::User,
        ]);
        Employee::factory()->forUser($this->user)->create([
            'company_id' => $this->company->id,
        ]);

        $this->calc = app(AttendanceCalcService::class);
    }

    public function test_late_with_assigned_shift(): void
    {
        $shift = Shift::create([
            'company_id' => $this->company->id,
            'name' => 'Sabah',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration_minutes' => 60,
            'is_active' => true,
        ]);

        $date = '2026-07-14';
        EmployeeShift::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_id' => $shift->id,
            'date' => $date,
        ]);

        $record = AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => '09:30',
            'clock_out' => '18:00',
            'status' => 'present',
        ]);

        $this->calc->recalculate($record);
        $record->refresh();

        $this->assertSame(AttendanceRecord::STATUS_LATE, $record->status);
        $this->assertSame(30, $record->late_minutes);
    }

    public function test_within_tolerance_is_present(): void
    {
        $date = '2026-07-14';
        $record = AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => '09:10',
            'clock_out' => '18:00',
            'status' => 'present',
        ]);

        $this->calc->recalculate($record);
        $record->refresh();

        $this->assertSame(AttendanceRecord::STATUS_PRESENT, $record->status);
        $this->assertSame(0, $record->late_minutes);
    }

    public function test_early_leave_minutes(): void
    {
        $date = '2026-07-14';
        $record = AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => '09:00',
            'clock_out' => '17:00',
            'status' => 'present',
        ]);

        $this->calc->recalculate($record);
        $record->refresh();

        $this->assertSame(AttendanceRecord::STATUS_EARLY_LEAVE, $record->status);
        $this->assertSame(60, $record->early_leave_minutes);
    }

    public function test_overtime_after_shift_end(): void
    {
        $shift = Shift::create([
            'company_id' => $this->company->id,
            'name' => 'Sabah',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration_minutes' => 60,
            'is_active' => true,
        ]);

        $date = '2026-07-14';
        EmployeeShift::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_id' => $shift->id,
            'date' => $date,
        ]);

        $record = AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => '09:00',
            'clock_out' => '20:00',
            'status' => 'present',
        ]);

        $this->calc->recalculate($record);
        $record->refresh();

        $this->assertSame(AttendanceRecord::STATUS_PRESENT, $record->status);
        $this->assertEquals(2.0, (float) $record->overtime_hours);
    }

    public function test_without_shift_uses_company_defaults(): void
    {
        $this->company->update([
            'settings' => [
                'general' => [
                    'default_work_start' => '08:00',
                    'default_work_end' => '17:00',
                    'late_tolerance_minutes' => 5,
                ],
            ],
        ]);

        $date = '2026-07-14';
        $record = AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => '08:20',
            'clock_out' => '17:00',
            'status' => 'present',
        ]);

        $this->calc->recalculate($record);
        $record->refresh();

        $this->assertSame(AttendanceRecord::STATUS_LATE, $record->status);
        $this->assertSame(20, $record->late_minutes);
    }

    public function test_nightly_job_marks_incomplete_idempotent(): void
    {
        $date = '2026-07-13';
        $record = AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => $date,
            'clock_in' => '09:00',
            'clock_out' => null,
            'status' => 'present',
            'missing_minutes' => 0,
        ]);

        $first = $this->calc->markIncompleteForDate($date);
        $record->refresh();
        $this->assertSame(1, $first);
        $this->assertSame(AttendanceRecord::STATUS_ABSENT, $record->status);
        $this->assertGreaterThan(0, $record->missing_minutes);

        $second = $this->calc->markIncompleteForDate($date);
        $this->assertSame(0, $second);
    }

    public function test_clock_out_triggers_calc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-14 18:30:00'));

        $shift = Shift::create([
            'company_id' => $this->company->id,
            'name' => 'Sabah',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'break_duration_minutes' => 60,
            'is_active' => true,
        ]);
        EmployeeShift::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'shift_id' => $shift->id,
            'date' => '2026-07-14',
        ]);

        AttendanceRecord::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date' => '2026-07-14',
            'clock_in' => '09:00',
            'status' => 'present',
        ]);

        $clock = app(AttendanceClockService::class);
        $result = $clock->clockOut($this->user);
        $record = $result['record'];

        $this->assertSame(AttendanceRecord::STATUS_PRESENT, $record->status);
        $this->assertEquals(0.5, (float) $record->overtime_hours);

        Carbon::setTestNow();
    }
}
