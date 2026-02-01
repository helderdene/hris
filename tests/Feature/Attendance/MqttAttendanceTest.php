<?php

use App\DataTransferObjects\AttendanceLogData;
use App\Events\AttendanceLogReceived;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendanceLogProcessor;
use App\Services\Attendance\MqttMessageParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForMqttAttendance(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('MqttMessageParser', function () {
    it('parses valid MQTT attendance message', function () {
        $parser = new MqttMessageParser;

        $topic = 'mqtt/face/2582493/Rec';
        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'PB250001',
                'personId' => '64',
                'RecordID' => '340',
                'VerifyStatus' => '1',
                'similarity1' => '85.699997',
                'time' => '2025-08-23 23:16:24',
                'direction' => 'in',
                'personName' => 'Test Employee',
                'facesluiceId' => '2582493',
            ],
        ]);

        $data = $parser->parse($topic, $payload);

        expect($data)->not->toBeNull()
            ->and($data->deviceIdentifier)->toBe('2582493')
            ->and($data->employeeCode)->toBe('PB250001')
            ->and($data->devicePersonId)->toBe('64')
            ->and($data->deviceRecordId)->toBe('340')
            ->and($data->confidence)->toBe(85.70)
            ->and($data->verifyStatus)->toBe('1')
            ->and($data->direction)->toBe('in')
            ->and($data->personName)->toBe('Test Employee');
    });

    it('extracts device identifier from topic pattern', function () {
        $parser = new MqttMessageParser;

        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'personId' => '1',
                'RecordID' => '1',
                'time' => '2025-01-01 08:00:00',
            ],
        ]);

        $data = $parser->parse('mqtt/face/12345/Rec', $payload);
        expect($data->deviceIdentifier)->toBe('12345');

        $data = $parser->parse('mqtt/face/9999999/Rec', $payload);
        expect($data->deviceIdentifier)->toBe('9999999');
    });

    it('returns null for invalid topic pattern', function () {
        $parser = new MqttMessageParser;

        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'personId' => '1',
                'RecordID' => '1',
                'time' => '2025-01-01 08:00:00',
            ],
        ]);

        expect($parser->parse('invalid/topic', $payload))->toBeNull();
        expect($parser->parse('mqtt/face/abc/Rec', $payload))->toBeNull();
        expect($parser->parse('mqtt/face//Rec', $payload))->toBeNull();
    });

    it('returns null for invalid JSON payload', function () {
        $parser = new MqttMessageParser;

        expect($parser->parse('mqtt/face/123/Rec', 'not json'))->toBeNull();
        expect($parser->parse('mqtt/face/123/Rec', '{invalid}'))->toBeNull();
    });

    it('returns null for non-RecPush operator', function () {
        $parser = new MqttMessageParser;

        $payload = json_encode([
            'operator' => 'HeartBeat',
            'info' => [],
        ]);

        expect($parser->parse('mqtt/face/123/Rec', $payload))->toBeNull();
    });

    it('handles typo in personName field (persionName)', function () {
        $parser = new MqttMessageParser;

        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'personId' => '1',
                'RecordID' => '1',
                'time' => '2025-01-01 08:00:00',
                'persionName' => 'Typo Name',
            ],
        ]);

        $data = $parser->parse('mqtt/face/123/Rec', $payload);
        expect($data->personName)->toBe('Typo Name');
    });

    it('clamps confidence values to valid range', function () {
        $parser = new MqttMessageParser;

        $payload = json_encode([
            'operator' => 'RecPush',
            'info' => [
                'customId' => 'EMP001',
                'personId' => '1',
                'RecordID' => '1',
                'time' => '2025-01-01 08:00:00',
                'similarity1' => '150.5',
            ],
        ]);

        $data = $parser->parse('mqtt/face/123/Rec', $payload);
        expect($data->confidence)->toBe(100.00);
    });
});

describe('AttendanceLogProcessor', function () {
    it('creates attendance log for registered device and matched employee', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMqttAttendance($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'device_identifier' => '2582493',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ]);
        $employee = Employee::factory()->create([
            'employee_number' => 'PB250001',
        ]);

        $data = new AttendanceLogData(
            deviceIdentifier: '2582493',
            devicePersonId: '64',
            deviceRecordId: '340',
            employeeCode: 'PB250001',
            confidence: 85.70,
            verifyStatus: '1',
            loggedAt: now(),
            direction: 'in',
            personName: 'Test Employee',
            capturedPhoto: null,
            rawPayload: null,
        );

        $processor = app(AttendanceLogProcessor::class);
        $log = $processor->process($data);

        expect($log)->not->toBeNull()
            ->and($log->biometric_device_id)->toBe($device->id)
            ->and($log->employee_id)->toBe($employee->id)
            ->and($log->employee_code)->toBe('PB250001')
            ->and($log->confidence)->toBe('85.70');

        $this->assertDatabaseHas('attendance_logs', [
            'biometric_device_id' => $device->id,
            'employee_id' => $employee->id,
            'employee_code' => 'PB250001',
        ]);
    });

    it('creates attendance log with null employee for unmatched employee code', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMqttAttendance($tenant);

        $workLocation = WorkLocation::factory()->create();
        BiometricDevice::factory()->create([
            'device_identifier' => '2582493',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ]);

        $data = new AttendanceLogData(
            deviceIdentifier: '2582493',
            devicePersonId: '64',
            deviceRecordId: '340',
            employeeCode: 'UNKNOWN999',
            confidence: 85.70,
            verifyStatus: '1',
            loggedAt: now(),
            direction: 'in',
            personName: 'Unknown Person',
            capturedPhoto: null,
            rawPayload: null,
        );

        $processor = app(AttendanceLogProcessor::class);
        $log = $processor->process($data);

        expect($log)->not->toBeNull()
            ->and($log->employee_id)->toBeNull()
            ->and($log->employee_code)->toBe('UNKNOWN999');
    });

    it('returns null for unknown device identifier', function () {
        Tenant::factory()->create();

        $data = new AttendanceLogData(
            deviceIdentifier: 'UNKNOWN-DEVICE',
            devicePersonId: '1',
            deviceRecordId: '1',
            employeeCode: 'EMP001',
            confidence: 90.0,
            verifyStatus: '1',
            loggedAt: now(),
            direction: 'in',
            personName: 'Test',
            capturedPhoto: null,
            rawPayload: null,
        );

        $processor = app(AttendanceLogProcessor::class);
        $log = $processor->process($data);

        expect($log)->toBeNull();
    });

    it('updates device last_seen_at timestamp', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMqttAttendance($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'device_identifier' => '2582493',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
            'last_seen_at' => null,
        ]);

        $data = new AttendanceLogData(
            deviceIdentifier: '2582493',
            devicePersonId: '1',
            deviceRecordId: '1',
            employeeCode: 'EMP001',
            confidence: 90.0,
            verifyStatus: '1',
            loggedAt: now(),
            direction: 'in',
            personName: 'Test',
            capturedPhoto: null,
            rawPayload: null,
        );

        $processor = app(AttendanceLogProcessor::class);
        $processor->process($data);

        $device->refresh();
        expect($device->last_seen_at)->not->toBeNull();
    });
});

describe('AttendanceLogReceived Event', function () {
    it('broadcasts attendance log event', function () {
        Event::fake([AttendanceLogReceived::class]);

        $tenant = Tenant::factory()->create();
        bindTenantContextForMqttAttendance($tenant);

        $workLocation = WorkLocation::factory()->create();
        $device = BiometricDevice::factory()->create([
            'device_identifier' => '2582493',
            'work_location_id' => $workLocation->id,
            'is_active' => true,
        ]);

        $log = AttendanceLog::create([
            'biometric_device_id' => $device->id,
            'employee_id' => null,
            'device_person_id' => '1',
            'device_record_id' => '1',
            'employee_code' => 'EMP001',
            'confidence' => 90.00,
            'verify_status' => '1',
            'logged_at' => now(),
            'direction' => 'in',
            'person_name' => 'Test',
        ]);

        event(new AttendanceLogReceived($log));

        Event::assertDispatched(AttendanceLogReceived::class, function ($event) use ($log) {
            return $event->log->id === $log->id;
        });
    });
});

describe('AttendanceLog Model', function () {
    it('can create attendance log with factory', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMqttAttendance($tenant);

        $log = AttendanceLog::factory()->create();

        expect($log)->toBeInstanceOf(AttendanceLog::class)
            ->and($log->biometric_device_id)->not->toBeNull()
            ->and($log->employee_code)->not->toBeEmpty();
    });

    it('belongs to biometric device', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMqttAttendance($tenant);

        $log = AttendanceLog::factory()->create();

        expect($log->biometricDevice)->toBeInstanceOf(BiometricDevice::class);
    });

    it('belongs to employee when matched', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMqttAttendance($tenant);

        $employee = Employee::factory()->create();
        $log = AttendanceLog::factory()->create([
            'employee_id' => $employee->id,
        ]);

        expect($log->employee)->toBeInstanceOf(Employee::class)
            ->and($log->employee->id)->toBe($employee->id);
    });

    it('can create unmatched attendance log', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForMqttAttendance($tenant);

        $log = AttendanceLog::factory()->unmatched()->create();

        expect($log->employee_id)->toBeNull()
            ->and($log->employee_code)->toContain('UNKNOWN');
    });
});
