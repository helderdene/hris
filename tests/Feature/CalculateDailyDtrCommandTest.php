<?php

use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Tenant;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

/**
 * Create an active schedule assignment with a past effective_date.
 *
 * SQLite stores date-cast columns as datetime strings (e.g. "2026-02-14 00:00:00"),
 * which breaks string comparisons against date-only strings. Using a past date
 * ensures the `<=` comparison in scopeActive works correctly.
 */
function createActiveAssignment(Employee $employee, WorkSchedule $schedule): EmployeeScheduleAssignment
{
    return EmployeeScheduleAssignment::factory()->forDateRange(
        now()->subMonth()->toDateString()
    )->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
    ]);
}

it('processes DTR for active employees with schedule assignments', function () {
    $tenant = Tenant::factory()->create();
    $schedule = WorkSchedule::factory()->create();
    $employee = Employee::factory()->active()->create();

    createActiveAssignment($employee, $schedule);

    $yesterday = now()->subDay()->startOfDay();

    Artisan::call('dtr:calculate-daily', ['--tenant' => $tenant->id]);

    expect(DailyTimeRecord::where('employee_id', $employee->id)
        ->whereDate('date', $yesterday->toDateString())
        ->exists())->toBeTrue();
});

it('skips employees without schedule assignments', function () {
    $tenant = Tenant::factory()->create();
    $employeeWithSchedule = Employee::factory()->active()->create();
    $employeeWithoutSchedule = Employee::factory()->active()->create();

    $schedule = WorkSchedule::factory()->create();
    createActiveAssignment($employeeWithSchedule, $schedule);

    Artisan::call('dtr:calculate-daily', ['--tenant' => $tenant->id]);

    $yesterday = now()->subDay()->toDateString();

    expect(DailyTimeRecord::where('employee_id', $employeeWithSchedule->id)
        ->whereDate('date', $yesterday)
        ->exists())->toBeTrue();

    expect(DailyTimeRecord::where('employee_id', $employeeWithoutSchedule->id)
        ->whereDate('date', $yesterday)
        ->exists())->toBeFalse();
});

it('skips inactive employees', function () {
    $tenant = Tenant::factory()->create();
    $schedule = WorkSchedule::factory()->create();

    $activeEmployee = Employee::factory()->active()->create();
    $terminatedEmployee = Employee::factory()->terminated()->create();

    createActiveAssignment($activeEmployee, $schedule);
    createActiveAssignment($terminatedEmployee, $schedule);

    Artisan::call('dtr:calculate-daily', ['--tenant' => $tenant->id]);

    $yesterday = now()->subDay()->toDateString();

    expect(DailyTimeRecord::where('employee_id', $activeEmployee->id)
        ->whereDate('date', $yesterday)
        ->exists())->toBeTrue();

    expect(DailyTimeRecord::where('employee_id', $terminatedEmployee->id)
        ->whereDate('date', $yesterday)
        ->exists())->toBeFalse();
});

it('processes a specific date when --date is provided', function () {
    $tenant = Tenant::factory()->create();
    $schedule = WorkSchedule::factory()->create();
    $employee = Employee::factory()->active()->create();

    // Use a date that falls within the schedule assignment range
    // (createActiveAssignment sets effective_date to now()->subMonth())
    $specificDate = now()->subDays(3)->toDateString();

    EmployeeScheduleAssignment::factory()->forDateRange(
        now()->subMonths(2)->toDateString()
    )->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
    ]);

    Artisan::call('dtr:calculate-daily', [
        '--tenant' => $tenant->id,
        '--date' => $specificDate,
    ]);

    expect(DailyTimeRecord::where('employee_id', $employee->id)
        ->whereDate('date', $specificDate)
        ->exists())->toBeTrue();
});

it('processes multiple days when --range is provided', function () {
    $tenant = Tenant::factory()->create();
    $schedule = WorkSchedule::factory()->create();
    $employee = Employee::factory()->active()->create();

    createActiveAssignment($employee, $schedule);

    Artisan::call('dtr:calculate-daily', [
        '--tenant' => $tenant->id,
        '--range' => 3,
    ]);

    $records = DailyTimeRecord::where('employee_id', $employee->id)->count();
    expect($records)->toBe(3);
});

it('succeeds with warning when no tenants found', function () {
    $this->artisan('dtr:calculate-daily', ['--tenant' => 999])
        ->expectsOutputToContain('No tenants found')
        ->assertSuccessful();
});

it('creates DTR with present status when attendance logs exist', function () {
    $tenant = Tenant::factory()->create();
    $schedule = WorkSchedule::factory()->create();
    $employee = Employee::factory()->active()->create();
    $device = BiometricDevice::factory()->create();

    createActiveAssignment($employee, $schedule);

    $yesterday = now()->subDay()->startOfDay();

    AttendanceLog::factory()->clockIn()->create([
        'employee_id' => $employee->id,
        'biometric_device_id' => $device->id,
        'logged_at' => $yesterday->copy()->setTime(8, 0),
    ]);
    AttendanceLog::factory()->clockOut()->create([
        'employee_id' => $employee->id,
        'biometric_device_id' => $device->id,
        'logged_at' => $yesterday->copy()->setTime(17, 0),
    ]);

    Artisan::call('dtr:calculate-daily', ['--tenant' => $tenant->id]);

    $dtr = DailyTimeRecord::where('employee_id', $employee->id)
        ->whereDate('date', $yesterday->toDateString())
        ->first();

    expect($dtr)->not->toBeNull()
        ->and($dtr->status->value)->toBe('present')
        ->and($dtr->first_in)->not->toBeNull()
        ->and($dtr->last_out)->not->toBeNull()
        ->and($dtr->total_work_minutes)->toBeGreaterThan(0);
});

it('updates existing DTR records on re-run', function () {
    $tenant = Tenant::factory()->create();
    $schedule = WorkSchedule::factory()->create();
    $employee = Employee::factory()->active()->create();

    createActiveAssignment($employee, $schedule);

    $yesterday = now()->subDay()->toDateString();

    Artisan::call('dtr:calculate-daily', ['--tenant' => $tenant->id]);
    Artisan::call('dtr:calculate-daily', ['--tenant' => $tenant->id]);

    expect(DailyTimeRecord::where('employee_id', $employee->id)
        ->whereDate('date', $yesterday)
        ->count())->toBe(1);
});
