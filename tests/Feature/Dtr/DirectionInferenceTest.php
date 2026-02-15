<?php

use App\Enums\DtrStatus;
use App\Enums\PunchType;
use App\Enums\TenantUserRole;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Dtr\DtrCalculationService;
use App\Services\Dtr\PunchPairProcessor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function bindTenantForDirectionTest(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createDirectionTestUser(Tenant $tenant): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

function createNullDirectionLogs(Employee $employee, array $times): \Illuminate\Support\Collection
{
    $device = \App\Models\BiometricDevice::factory()->create();

    return collect($times)->map(fn (string $time) => AttendanceLog::factory()->create([
        'employee_id' => $employee->id,
        'biometric_device_id' => $device->id,
        'logged_at' => $time,
        'direction' => null,
    ]));
}

/**
 * Build schedule events matching the default WorkSchedule factory config
 * (08:00-17:00, break 12:00 for 60 min).
 *
 * @return array<int, array{time: Carbon, direction: PunchType}>
 */
function defaultScheduleEvents(string $dateStr = '2025-01-06'): array
{
    return [
        ['time' => Carbon::parse("$dateStr 08:00:00"), 'direction' => PunchType::In],
        ['time' => Carbon::parse("$dateStr 12:00:00"), 'direction' => PunchType::Out],
        ['time' => Carbon::parse("$dateStr 13:00:00"), 'direction' => PunchType::In],
        ['time' => Carbon::parse("$dateStr 17:00:00"), 'direction' => PunchType::Out],
    ];
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('PunchPairProcessor::collapseDuplicateScans', function () {
    it('collapses consecutive null-direction logs within threshold', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, [
            '2025-01-06 07:58:00',
            '2025-01-06 07:59:30', // 1.5 min later — duplicate
            '2025-01-06 17:02:00',
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->collapseDuplicateScans($logs);

        expect($result)->toHaveCount(2)
            ->and(Carbon::parse($result[0]->logged_at)->format('H:i:s'))->toBe('07:58:00')
            ->and(Carbon::parse($result[1]->logged_at)->format('H:i:s'))->toBe('17:02:00');
    });

    it('collapses chains of rapid scans keeping only the first', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, [
            '2025-01-06 07:58:00',
            '2025-01-06 07:58:45', // duplicate
            '2025-01-06 07:59:30', // duplicate of duplicate
            '2025-01-06 17:02:00',
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->collapseDuplicateScans($logs);

        expect($result)->toHaveCount(2);
    });

    it('does not collapse logs that are beyond threshold', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, [
            '2025-01-06 07:58:00',
            '2025-01-06 08:28:00', // 30 min later — not duplicate
            '2025-01-06 17:02:00',
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->collapseDuplicateScans($logs);

        expect($result)->toHaveCount(3);
    });

    it('never collapses logs with explicit direction', function () {
        $employee = Employee::factory()->create();
        $device = \App\Models\BiometricDevice::factory()->create();

        $logs = collect([
            AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'biometric_device_id' => $device->id,
                'logged_at' => '2025-01-06 07:58:00',
                'direction' => 'in',
            ]),
            AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'biometric_device_id' => $device->id,
                'logged_at' => '2025-01-06 07:59:00',
                'direction' => 'in',
            ]),
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->collapseDuplicateScans($logs);

        expect($result)->toHaveCount(2);
    });
});

describe('PunchPairProcessor::matchToSchedule', function () {
    it('matches 2 punches to shift start/end', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, [
            '2025-01-06 07:55:00',
            '2025-01-06 17:05:00',
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->matchToSchedule($logs, defaultScheduleEvents());

        expect($result['logs'])->toHaveCount(2)
            ->and($result['droppedCount'])->toBe(0)
            ->and($result['logs'][0]->direction)->toBe('in')
            ->and($result['logs'][1]->direction)->toBe('out');
    });

    it('matches 4 punches to shift start, break out, break in, shift end', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, [
            '2025-01-06 07:55:00',
            '2025-01-06 12:03:00',
            '2025-01-06 13:00:00',
            '2025-01-06 17:05:00',
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->matchToSchedule($logs, defaultScheduleEvents());

        expect($result['logs'])->toHaveCount(4)
            ->and($result['droppedCount'])->toBe(0)
            ->and($result['logs'][0]->direction)->toBe('in')
            ->and($result['logs'][1]->direction)->toBe('out')
            ->and($result['logs'][2]->direction)->toBe('in')
            ->and($result['logs'][3]->direction)->toBe('out');
    });

    it('drops unmatched anomalous punches and reports count', function () {
        // 5 punches: 07:58, 08:28, 12:03, 14:00, 17:02
        // Schedule events: IN@08:00, OUT@12:00, IN@13:00, OUT@17:00
        // 08:28 doesn't match any remaining event → dropped
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, [
            '2025-01-06 07:58:00',
            '2025-01-06 08:28:00',
            '2025-01-06 12:03:00',
            '2025-01-06 14:00:00',
            '2025-01-06 17:02:00',
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->matchToSchedule($logs, defaultScheduleEvents());

        expect($result['droppedCount'])->toBe(1)
            ->and($result['logs'])->toHaveCount(4)
            ->and($result['logs'][0]->direction)->toBe('in')   // 07:58 → shift start
            ->and($result['logs'][1]->direction)->toBe('out')  // 12:03 → break start
            ->and($result['logs'][2]->direction)->toBe('in')   // 14:00 → break end
            ->and($result['logs'][3]->direction)->toBe('out'); // 17:02 → shift end
    });

    it('preserves logs with explicit direction', function () {
        $employee = Employee::factory()->create();
        $device = \App\Models\BiometricDevice::factory()->create();

        $logs = collect([
            AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'biometric_device_id' => $device->id,
                'logged_at' => '2025-01-06 07:55:00',
                'direction' => 'in',
            ]),
            AttendanceLog::factory()->create([
                'employee_id' => $employee->id,
                'biometric_device_id' => $device->id,
                'logged_at' => '2025-01-06 17:05:00',
                'direction' => null,
            ]),
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->matchToSchedule($logs, defaultScheduleEvents());

        expect($result['logs'])->toHaveCount(2)
            ->and($result['logs'][0]->direction)->toBe('in')
            ->and($result['logs'][1]->direction)->toBe('out');
    });

    it('handles single morning punch', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, ['2025-01-06 07:55:00']);

        $processor = new PunchPairProcessor;
        $result = $processor->matchToSchedule($logs, defaultScheduleEvents());

        expect($result['logs'])->toHaveCount(1)
            ->and($result['logs'][0]->direction)->toBe('in');
    });

    it('handles single evening punch', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, ['2025-01-06 17:05:00']);

        $processor = new PunchPairProcessor;
        $result = $processor->matchToSchedule($logs, defaultScheduleEvents());

        expect($result['logs'])->toHaveCount(1)
            ->and($result['logs'][0]->direction)->toBe('out');
    });
});

describe('PunchPairProcessor::inferDirections (no-schedule fallback)', function () {
    it('alternates IN/OUT when no schedule is available', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, [
            '2025-01-06 08:00:00',
            '2025-01-06 17:00:00',
        ]);

        $processor = new PunchPairProcessor;
        $processor->inferDirections($logs);

        expect($logs[0]->direction)->toBe('in')
            ->and($logs[1]->direction)->toBe('out');
    });
});

describe('PunchPairProcessor::process null-direction fallback', function () {
    it('treats remaining null-direction logs as alternating IN/OUT', function () {
        $employee = Employee::factory()->create();
        $logs = createNullDirectionLogs($employee, [
            '2025-01-06 08:00:00',
            '2025-01-06 17:00:00',
        ]);

        $processor = new PunchPairProcessor;
        $result = $processor->process($logs);

        expect($result['first_in'])->not->toBeNull()
            ->and($result['last_out'])->not->toBeNull()
            ->and($result['pairs'])->toHaveCount(1)
            ->and($result['pairs'][0]['in'])->not->toBeNull()
            ->and($result['pairs'][0]['out'])->not->toBeNull();
    });
});

describe('DtrCalculationService integration', function () {
    it('calculates DTR with 2 null-direction punches matched to schedule', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDirectionTest($tenant);

        $user = createDirectionTestUser($tenant);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $schedule = WorkSchedule::factory()->create();

        $date = Carbon::parse('2025-01-06'); // Monday
        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => $date->copy()->subMonth()->toDateString(),
        ]);

        $device = \App\Models\BiometricDevice::factory()->create();
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 07:55:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 17:05:00',
            'direction' => null,
        ]);

        $service = app(DtrCalculationService::class);
        $dtr = $service->calculateForDate($employee, $date);

        expect($dtr->status)->toBe(DtrStatus::Present)
            ->and($dtr->first_in)->not->toBeNull()
            ->and($dtr->last_out)->not->toBeNull()
            ->and($dtr->total_work_minutes)->toBeGreaterThan(0)
            ->and($dtr->needs_review)->toBeFalse();
    });

    it('calculates DTR with 4 null-direction punches (typical day with break)', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDirectionTest($tenant);

        $user = createDirectionTestUser($tenant);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $schedule = WorkSchedule::factory()->create();

        $date = Carbon::parse('2025-01-06');
        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => $date->copy()->subMonth()->toDateString(),
        ]);

        $device = \App\Models\BiometricDevice::factory()->create();

        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 07:55:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 12:00:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 13:00:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 17:05:00',
            'direction' => null,
        ]);

        $service = app(DtrCalculationService::class);
        $dtr = $service->calculateForDate($employee, $date);

        expect($dtr->status)->toBe(DtrStatus::Present)
            ->and($dtr->first_in)->not->toBeNull()
            ->and($dtr->last_out)->not->toBeNull()
            ->and($dtr->punches)->toHaveCount(4)
            ->and($dtr->needs_review)->toBeFalse();
    });

    it('drops anomalous punch and flags needs_review for 5-punch day', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDirectionTest($tenant);

        $user = createDirectionTestUser($tenant);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $schedule = WorkSchedule::factory()->create();

        $date = Carbon::parse('2025-01-06');
        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => $date->copy()->subMonth()->toDateString(),
        ]);

        $device = \App\Models\BiometricDevice::factory()->create();

        // 5 punches: arrival, anomaly at 08:28, lunch out, lunch in, departure
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 07:58:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 08:28:00', // anomalous
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 12:03:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 14:00:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 17:02:00',
            'direction' => null,
        ]);

        $service = app(DtrCalculationService::class);
        $dtr = $service->calculateForDate($employee, $date);

        expect($dtr->status)->toBe(DtrStatus::Present)
            ->and($dtr->punches)->toHaveCount(4)       // 08:28 dropped
            ->and($dtr->needs_review)->toBeTrue()
            ->and($dtr->review_reason)->toContain('unmatched');
    });

    it('collapses duplicate scans before matching to schedule', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDirectionTest($tenant);

        $user = createDirectionTestUser($tenant);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        $schedule = WorkSchedule::factory()->create();

        $date = Carbon::parse('2025-01-06');
        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => $date->copy()->subMonth()->toDateString(),
        ]);

        $device = \App\Models\BiometricDevice::factory()->create();

        // Arrival + immediate duplicate + departure
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 07:58:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 07:59:00', // duplicate scan
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-01-06 17:02:00',
            'direction' => null,
        ]);

        $service = app(DtrCalculationService::class);
        $dtr = $service->calculateForDate($employee, $date);

        // Duplicate collapsed → 2 punches matched to schedule → IN, OUT
        expect($dtr->status)->toBe(DtrStatus::Present)
            ->and($dtr->first_in)->not->toBeNull()
            ->and($dtr->last_out)->not->toBeNull()
            ->and($dtr->punches)->toHaveCount(2)
            ->and($dtr->needs_review)->toBeFalse();
    });
});

describe('Cross-midnight schedule punch handling', function () {
    it('includes next-day punch-out in cross-midnight schedule DTR', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDirectionTest($tenant);

        $user = createDirectionTestUser($tenant);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        // Schedule: 17:00 - 00:00 (crosses midnight)
        $schedule = WorkSchedule::factory()->create([
            'time_configuration' => [
                'start_time' => '17:00',
                'end_time' => '00:00',
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'break' => [
                    'start_time' => null,
                    'duration_minutes' => 0,
                ],
            ],
        ]);

        $workDate = Carbon::parse('2025-02-13'); // Thursday
        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => $workDate->copy()->subMonth()->toDateString(),
        ]);

        $device = \App\Models\BiometricDevice::factory()->create();

        // Punch IN at 17:02 on Feb 13
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-02-13 17:02:00',
            'direction' => null,
        ]);
        // Punch OUT at 01:04 on Feb 14 (next calendar day)
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-02-14 01:04:00',
            'direction' => null,
        ]);

        $service = app(DtrCalculationService::class);
        $dtr = $service->calculateForDate($employee, $workDate);

        expect($dtr->status)->toBe(DtrStatus::Present)
            ->and($dtr->first_in)->not->toBeNull()
            ->and($dtr->last_out)->not->toBeNull()
            ->and(Carbon::parse($dtr->first_in)->format('Y-m-d H:i'))->toBe('2025-02-13 17:02')
            ->and(Carbon::parse($dtr->last_out)->format('Y-m-d H:i'))->toBe('2025-02-14 01:04')
            ->and($dtr->punches)->toHaveCount(2)
            ->and($dtr->total_work_minutes)->toBeGreaterThan(0);
    });

    it('does not double-count next-day punch that belongs to previous cross-midnight schedule', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDirectionTest($tenant);

        $user = createDirectionTestUser($tenant);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        // Schedule: 17:00 - 00:00 (crosses midnight)
        $schedule = WorkSchedule::factory()->create([
            'time_configuration' => [
                'start_time' => '17:00',
                'end_time' => '00:00',
                'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'break' => [
                    'start_time' => null,
                    'duration_minutes' => 0,
                ],
            ],
        ]);

        $workDate = Carbon::parse('2025-02-13'); // Thursday
        $nextDate = Carbon::parse('2025-02-14'); // Friday
        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => $workDate->copy()->subMonth()->toDateString(),
        ]);

        $device = \App\Models\BiometricDevice::factory()->create();

        // Feb 13 punches: IN at 17:02, OUT at 01:04 next day
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-02-13 17:02:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-02-14 01:04:00',
            'direction' => null,
        ]);

        // Feb 14 punches: IN at 17:00, OUT at 23:50
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-02-14 17:00:00',
            'direction' => null,
        ]);
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-02-14 23:50:00',
            'direction' => null,
        ]);

        $service = app(DtrCalculationService::class);

        // Feb 14's DTR should NOT claim the 01:04 AM punch
        $dtr14 = $service->calculateForDate($employee, $nextDate);

        expect($dtr14->status)->toBe(DtrStatus::Present)
            ->and(Carbon::parse($dtr14->first_in)->format('H:i'))->toBe('17:00')
            ->and($dtr14->punches)->toHaveCount(2);
    });

    it('handles cross-midnight with punches both before and after midnight', function () {
        $tenant = Tenant::factory()->create(['slug' => 'testco']);
        bindTenantForDirectionTest($tenant);

        $user = createDirectionTestUser($tenant);
        $employee = Employee::factory()->create(['user_id' => $user->id]);

        // Shifting schedule with Night Shift 22:00 - 06:00
        $schedule = WorkSchedule::factory()->shifting()->create();

        $workDate = Carbon::parse('2025-02-13'); // Thursday
        EmployeeScheduleAssignment::factory()->create([
            'employee_id' => $employee->id,
            'work_schedule_id' => $schedule->id,
            'effective_date' => $workDate->copy()->subMonth()->toDateString(),
            'shift_name' => 'Night Shift',
        ]);

        $device = \App\Models\BiometricDevice::factory()->create();

        // Punch IN at 21:55 on Feb 13
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-02-13 21:55:00',
            'direction' => null,
        ]);
        // Punch OUT at 06:05 on Feb 14 (next calendar day)
        AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
            'biometric_device_id' => $device->id,
            'logged_at' => '2025-02-14 06:05:00',
            'direction' => null,
        ]);

        $service = app(DtrCalculationService::class);
        $dtr = $service->calculateForDate($employee, $workDate);

        expect($dtr->status)->toBe(DtrStatus::Present)
            ->and($dtr->first_in)->not->toBeNull()
            ->and($dtr->last_out)->not->toBeNull()
            ->and(Carbon::parse($dtr->first_in)->format('Y-m-d H:i'))->toBe('2025-02-13 21:55')
            ->and(Carbon::parse($dtr->last_out)->format('Y-m-d H:i'))->toBe('2025-02-14 06:05')
            ->and($dtr->total_work_minutes)->toBeGreaterThan(0);
    });
});
