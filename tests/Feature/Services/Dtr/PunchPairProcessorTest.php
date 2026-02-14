<?php

use App\Enums\PunchType;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Services\Dtr\PunchPairProcessor;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

/**
 * Create a null-direction attendance log for testing.
 */
function createNullDirLog(Employee $employee, BiometricDevice $device, string $loggedAt): AttendanceLog
{
    return AttendanceLog::factory()->create([
        'employee_id' => $employee->id,
        'biometric_device_id' => $device->id,
        'direction' => null,
        'logged_at' => $loggedAt,
    ]);
}

it('assigns first punch as IN and last punch as OUT for all-null-direction logs', function () {
    $employee = Employee::factory()->active()->create();
    $device = BiometricDevice::factory()->create();

    $log1 = createNullDirLog($employee, $device, '2025-02-13 19:35:00');
    $log2 = createNullDirLog($employee, $device, '2025-02-13 20:37:00');
    $log3 = createNullDirLog($employee, $device, '2025-02-13 23:38:00');

    $logs = collect([$log1, $log2, $log3]);

    // Closer schedule events: 17:00 IN, 20:00 OUT, 21:00 IN, 00:00+1 OUT
    $scheduleEvents = [
        ['time' => Carbon::parse('2025-02-13 17:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 20:00:00'), 'direction' => PunchType::Out],
        ['time' => Carbon::parse('2025-02-13 21:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-14 00:00:00'), 'direction' => PunchType::Out],
    ];

    $processor = app(PunchPairProcessor::class);
    $result = $processor->matchToSchedule($logs, $scheduleEvents);

    expect($result['logs'])->toHaveCount(3)
        ->and($result['droppedCount'])->toBe(0);

    $sorted = $result['logs']->sortBy('logged_at')->values();

    // First punch (19:35) should be IN
    expect($sorted[0]->direction)->toBe('in');
    // Middle punch (20:37) should be OUT (matched to break 20:00)
    expect($sorted[1]->direction)->toBe('out');
    // Last punch (23:38) should be OUT (boundary)
    expect($sorted[2]->direction)->toBe('out');
});

it('produces correct first_in and last_out for cross-midnight closer schedule', function () {
    $employee = Employee::factory()->active()->create();
    $device = BiometricDevice::factory()->create();

    $log1 = createNullDirLog($employee, $device, '2025-02-13 19:35:00');
    $log2 = createNullDirLog($employee, $device, '2025-02-13 20:37:00');
    $log3 = createNullDirLog($employee, $device, '2025-02-13 23:38:00');

    $logs = collect([$log1, $log2, $log3]);

    $scheduleEvents = [
        ['time' => Carbon::parse('2025-02-13 17:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 20:00:00'), 'direction' => PunchType::Out],
        ['time' => Carbon::parse('2025-02-13 21:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-14 00:00:00'), 'direction' => PunchType::Out],
    ];

    $processor = app(PunchPairProcessor::class);
    $matchResult = $processor->matchToSchedule($logs, $scheduleEvents);
    $processResult = $processor->process($matchResult['logs']);

    // First IN should be the first punch (19:35), not the second
    expect($processResult['first_in']->toDateTimeString())->toBe('2025-02-13 19:35:00');
    // Last OUT should be the last punch (23:38)
    expect($processResult['last_out']->toDateTimeString())->toBe('2025-02-13 23:38:00');
});

it('handles four null-direction punches with break for cross-midnight schedule', function () {
    $employee = Employee::factory()->active()->create();
    $device = BiometricDevice::factory()->create();

    $log1 = createNullDirLog($employee, $device, '2025-02-13 19:35:00');
    $log2 = createNullDirLog($employee, $device, '2025-02-13 20:37:00');
    $log3 = createNullDirLog($employee, $device, '2025-02-13 21:05:00');
    $log4 = createNullDirLog($employee, $device, '2025-02-13 23:38:00');

    $logs = collect([$log1, $log2, $log3, $log4]);

    $scheduleEvents = [
        ['time' => Carbon::parse('2025-02-13 17:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 20:00:00'), 'direction' => PunchType::Out],
        ['time' => Carbon::parse('2025-02-13 21:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-14 00:00:00'), 'direction' => PunchType::Out],
    ];

    $processor = app(PunchPairProcessor::class);
    $matchResult = $processor->matchToSchedule($logs, $scheduleEvents);
    $processResult = $processor->process($matchResult['logs']);

    // Should create two complete pairs
    expect($processResult['first_in']->toDateTimeString())->toBe('2025-02-13 19:35:00')
        ->and($processResult['last_out']->toDateTimeString())->toBe('2025-02-13 23:38:00');

    // Two complete pairs: 19:35-20:37 and 21:05-23:38
    $completePairs = array_filter($processResult['pairs'], fn ($p) => $p['in'] !== null && $p['out'] !== null);
    expect(count($completePairs))->toBe(2);

    $totalWork = $processor->calculateTotalWorkMinutes($processResult['pairs']);
    expect($totalWork)->toBeGreaterThan(0);
});

it('preserves explicit direction punches during boundary matching', function () {
    $employee = Employee::factory()->active()->create();
    $device = BiometricDevice::factory()->create();

    // First punch has explicit IN direction
    $log1 = AttendanceLog::factory()->clockIn()->create([
        'employee_id' => $employee->id,
        'biometric_device_id' => $device->id,
        'logged_at' => '2025-02-13 08:05:00',
    ]);
    // Middle punch null direction
    $log2 = createNullDirLog($employee, $device, '2025-02-13 12:05:00');
    // Last punch null direction
    $log3 = createNullDirLog($employee, $device, '2025-02-13 17:10:00');

    $logs = collect([$log1, $log2, $log3]);

    $scheduleEvents = [
        ['time' => Carbon::parse('2025-02-13 08:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 12:00:00'), 'direction' => PunchType::Out],
        ['time' => Carbon::parse('2025-02-13 13:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 17:00:00'), 'direction' => PunchType::Out],
    ];

    $processor = app(PunchPairProcessor::class);
    $result = $processor->matchToSchedule($logs, $scheduleEvents);

    $sorted = $result['logs']->sortBy('logged_at')->values();

    // Explicit IN should be preserved
    expect($sorted[0]->direction)->toBe('in');
    // Null-direction punches should use proximity (not boundary) since not all are null
    expect($sorted[1]->direction)->toBe('out');
    expect($sorted[2]->direction)->toBe('out');
});

it('infers direction via alternating for unmatched punches instead of dropping', function () {
    $employee = Employee::factory()->active()->create();
    $device = BiometricDevice::factory()->create();

    // Punches far from any schedule event
    $log1 = createNullDirLog($employee, $device, '2025-02-13 10:00:00');
    $log2 = createNullDirLog($employee, $device, '2025-02-13 15:00:00');

    $logs = collect([$log1, $log2]);

    // Schedule events at very different times (all > 90 min tolerance)
    $scheduleEvents = [
        ['time' => Carbon::parse('2025-02-13 06:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 22:00:00'), 'direction' => PunchType::Out],
    ];

    $processor = app(PunchPairProcessor::class);
    $result = $processor->matchToSchedule($logs, $scheduleEvents);

    // All punches should be kept (not dropped), with boundary matching
    expect($result['logs'])->toHaveCount(2)
        ->and($result['droppedCount'])->toBe(0);

    $sorted = $result['logs']->sortBy('logged_at')->values();

    // First = IN (boundary), Last = OUT (boundary)
    expect($sorted[0]->direction)->toBe('in');
    expect($sorted[1]->direction)->toBe('out');
});

it('handles normal schedule with proximity matching correctly', function () {
    $employee = Employee::factory()->active()->create();
    $device = BiometricDevice::factory()->create();

    $log1 = createNullDirLog($employee, $device, '2025-02-13 07:55:00');
    $log2 = createNullDirLog($employee, $device, '2025-02-13 12:05:00');
    $log3 = createNullDirLog($employee, $device, '2025-02-13 13:02:00');
    $log4 = createNullDirLog($employee, $device, '2025-02-13 17:10:00');

    $logs = collect([$log1, $log2, $log3, $log4]);

    $scheduleEvents = [
        ['time' => Carbon::parse('2025-02-13 08:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 12:00:00'), 'direction' => PunchType::Out],
        ['time' => Carbon::parse('2025-02-13 13:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 17:00:00'), 'direction' => PunchType::Out],
    ];

    $processor = app(PunchPairProcessor::class);
    $result = $processor->matchToSchedule($logs, $scheduleEvents);

    expect($result['droppedCount'])->toBe(0);

    $sorted = $result['logs']->sortBy('logged_at')->values();

    expect($sorted[0]->direction)->toBe('in');
    expect($sorted[1]->direction)->toBe('out');
    expect($sorted[2]->direction)->toBe('in');
    expect($sorted[3]->direction)->toBe('out');
});

it('handles single null-direction punch as IN', function () {
    $employee = Employee::factory()->active()->create();
    $device = BiometricDevice::factory()->create();

    $log1 = createNullDirLog($employee, $device, '2025-02-13 08:05:00');

    $logs = collect([$log1]);

    $scheduleEvents = [
        ['time' => Carbon::parse('2025-02-13 08:00:00'), 'direction' => PunchType::In],
        ['time' => Carbon::parse('2025-02-13 17:00:00'), 'direction' => PunchType::Out],
    ];

    $processor = app(PunchPairProcessor::class);
    $result = $processor->matchToSchedule($logs, $scheduleEvents);

    expect($result['logs'])->toHaveCount(1);

    // Single punch should be IN (boundary)
    expect($result['logs']->first()->direction)->toBe('in');
});
