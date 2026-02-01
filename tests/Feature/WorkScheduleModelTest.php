<?php

use App\Enums\ScheduleType;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

/**
 * Test 1: WorkSchedule creation with all schedule types (Fixed, Flexible, Shifting, Compressed)
 */
it('can create work schedules with all schedule types', function () {
    $scheduleTypes = [
        ScheduleType::Fixed,
        ScheduleType::Flexible,
        ScheduleType::Shifting,
        ScheduleType::Compressed,
    ];

    foreach ($scheduleTypes as $type) {
        $schedule = WorkSchedule::factory()->create([
            'schedule_type' => $type,
        ]);

        expect($schedule)->toBeInstanceOf(WorkSchedule::class)
            ->and($schedule->schedule_type)->toBe($type)
            ->and($schedule->schedule_type->label())->toBeString();
    }

    expect(WorkSchedule::count())->toBe(4);
});

/**
 * Test 2: JSON configuration field casting for time_configuration and overtime_rules
 */
it('casts JSON configuration fields correctly', function () {
    $timeConfiguration = [
        'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'half_day_saturday' => false,
        'start_time' => '08:00',
        'end_time' => '17:00',
        'saturday_end_time' => null,
        'break' => [
            'start_time' => '12:00',
            'duration_minutes' => 60,
        ],
    ];

    $overtimeRules = [
        'daily_threshold_hours' => 8,
        'weekly_threshold_hours' => 40,
        'regular_multiplier' => 1.25,
        'rest_day_multiplier' => 1.30,
        'holiday_multiplier' => 2.0,
    ];

    $nightDifferential = [
        'enabled' => true,
        'start_time' => '22:00',
        'end_time' => '06:00',
        'rate_multiplier' => 1.10,
    ];

    $schedule = WorkSchedule::factory()->create([
        'time_configuration' => $timeConfiguration,
        'overtime_rules' => $overtimeRules,
        'night_differential' => $nightDifferential,
    ]);

    // Reload from database to test casting
    $schedule->refresh();

    expect($schedule->time_configuration)->toBeArray()
        ->and($schedule->time_configuration['work_days'])->toBe(['monday', 'tuesday', 'wednesday', 'thursday', 'friday'])
        ->and($schedule->time_configuration['break']['duration_minutes'])->toBe(60)
        ->and($schedule->overtime_rules)->toBeArray()
        ->and($schedule->overtime_rules['daily_threshold_hours'])->toBe(8)
        ->and($schedule->overtime_rules['regular_multiplier'])->toBe(1.25)
        ->and($schedule->night_differential)->toBeArray()
        ->and($schedule->night_differential['enabled'])->toBeTrue()
        ->and($schedule->night_differential['rate_multiplier'])->toBe(1.10);
});

/**
 * Test 3: scopeActive query scope
 */
it('filters active work schedules using scopeActive', function () {
    WorkSchedule::factory()->count(3)->create(['status' => 'active']);
    WorkSchedule::factory()->count(2)->create(['status' => 'inactive']);

    $activeSchedules = WorkSchedule::active()->get();

    expect($activeSchedules)->toHaveCount(3)
        ->and($activeSchedules->every(fn ($schedule) => $schedule->status === 'active'))->toBeTrue();
});

/**
 * Test 4: WorkSchedule to EmployeeScheduleAssignment relationship
 */
it('has many employee schedule assignments', function () {
    $schedule = WorkSchedule::factory()->create();
    $employees = Employee::factory()->count(3)->create();

    foreach ($employees as $employee) {
        EmployeeScheduleAssignment::factory()->create([
            'work_schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
        ]);
    }

    $schedule->refresh();

    expect($schedule->employeeScheduleAssignments)->toHaveCount(3)
        ->and($schedule->employeeScheduleAssignments->first())->toBeInstanceOf(EmployeeScheduleAssignment::class);
});

/**
 * Test 5: EmployeeScheduleAssignment effective dating logic (one active per employee)
 */
it('supports effective dating with active assignment scope', function () {
    $employee = Employee::factory()->create();
    $schedule1 = WorkSchedule::factory()->create(['name' => 'Old Schedule']);
    $schedule2 = WorkSchedule::factory()->create(['name' => 'Current Schedule']);

    $today = now()->toDateString();
    $yesterday = now()->subDay()->toDateString();
    $sixMonthsAgo = now()->subMonths(6)->toDateString();
    $nextMonth = now()->addMonth()->toDateString();

    // Past assignment (ended yesterday)
    EmployeeScheduleAssignment::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule1->id,
        'effective_date' => $sixMonthsAgo,
        'end_date' => $yesterday,
    ]);

    // Current active assignment (started yesterday, no end date)
    EmployeeScheduleAssignment::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule2->id,
        'effective_date' => $yesterday,
        'end_date' => null,
    ]);

    // Future assignment (starts next month)
    EmployeeScheduleAssignment::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule1->id,
        'effective_date' => $nextMonth,
        'end_date' => null,
    ]);

    $activeAssignments = EmployeeScheduleAssignment::active()->where('employee_id', $employee->id)->get();

    expect($activeAssignments)->toHaveCount(1)
        ->and($activeAssignments->first()->workSchedule->name)->toBe('Current Schedule');
});

/**
 * Test 6: EmployeeScheduleAssignment belongs to Employee and WorkSchedule
 */
it('has belongs to relationships for employee and work schedule', function () {
    $employee = Employee::factory()->create();
    $schedule = WorkSchedule::factory()->create();

    $assignment = EmployeeScheduleAssignment::factory()->create([
        'employee_id' => $employee->id,
        'work_schedule_id' => $schedule->id,
    ]);

    expect($assignment->employee)->toBeInstanceOf(Employee::class)
        ->and($assignment->employee->id)->toBe($employee->id)
        ->and($assignment->workSchedule)->toBeInstanceOf(WorkSchedule::class)
        ->and($assignment->workSchedule->id)->toBe($schedule->id);
});
