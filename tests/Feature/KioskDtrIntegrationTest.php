<?php

use App\Enums\AttendanceSource;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Services\Kiosk\KioskClockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Kiosk DTR Integration', function () {
    it('kiosk clock creates attendance log with correct source', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $location = WorkLocation::factory()->create();
        $kiosk = Kiosk::factory()->forWorkLocation($location)->create();
        $employee = Employee::factory()->create(['work_location_id' => $location->id]);

        $service = app(KioskClockService::class);
        $log = $service->clock($employee, 'in', $kiosk);

        expect($log->source)->toBe(AttendanceSource::Kiosk);
        expect($log->kiosk_id)->toBe($kiosk->id);
        expect($log->direction)->toBe('in');
        expect($log->employee_id)->toBe($employee->id);
    });

    it('self-service clock creates attendance log with correct source', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $employee = Employee::factory()->create();

        $service = app(KioskClockService::class);
        $log = $service->clockSelfService($employee, 'out');

        expect($log->source)->toBe(AttendanceSource::SelfService);
        expect($log->kiosk_id)->toBeNull();
        expect($log->direction)->toBe('out');
    });

    it('kiosk and biometric logs coexist in DTR queries', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $location = WorkLocation::factory()->create();
        $kiosk = Kiosk::factory()->forWorkLocation($location)->create();
        $employee = Employee::factory()->create(['work_location_id' => $location->id]);

        // Create a biometric log
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_number,
            'person_name' => $employee->full_name,
            'device_person_id' => "bio-{$employee->id}",
            'direction' => 'in',
            'logged_at' => now()->setTime(8, 0),
            'source' => AttendanceSource::Biometric,
        ]);

        // Create a kiosk log
        $service = app(KioskClockService::class);
        $service->clock($employee, 'out', $kiosk);

        $logs = AttendanceLog::where('employee_id', $employee->id)
            ->orderBy('logged_at')
            ->get();

        expect($logs)->toHaveCount(2);
        expect($logs[0]->source)->toBe(AttendanceSource::Biometric);
        expect($logs[1]->source)->toBe(AttendanceSource::Kiosk);
    });

    it('checkCooldown returns true when within cooldown window', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $employee = Employee::factory()->create();

        $service = app(KioskClockService::class);
        $service->clockSelfService($employee, 'in');

        expect($service->checkCooldown($employee, 5))->toBeTrue();
    });

    it('checkCooldown returns false when no recent logs', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $employee = Employee::factory()->create();
        $service = app(KioskClockService::class);

        expect($service->checkCooldown($employee, 5))->toBeFalse();
    });

    it('getLastPunch returns most recent attendance log', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $employee = Employee::factory()->create();
        $kiosk = Kiosk::factory()->create();

        $service = app(KioskClockService::class);

        expect($service->getLastPunch($employee))->toBeNull();

        $service->clock($employee, 'in', $kiosk);
        $lastPunch = $service->getLastPunch($employee);

        expect($lastPunch)->not->toBeNull();
        expect($lastPunch->direction)->toBe('in');
    });

    it('attendance log source filter works correctly', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $employee = Employee::factory()->create();
        $kiosk = Kiosk::factory()->create();

        // Create logs from different sources
        AttendanceLog::create([
            'employee_id' => $employee->id,
            'employee_code' => $employee->employee_number,
            'person_name' => $employee->full_name,
            'device_person_id' => "bio-{$employee->id}",
            'direction' => 'in',
            'logged_at' => now()->subHours(3),
            'source' => AttendanceSource::Biometric,
        ]);

        $service = app(KioskClockService::class);
        $service->clock($employee, 'out', $kiosk);
        $service->clockSelfService($employee, 'in');

        $biometricLogs = AttendanceLog::where('source', AttendanceSource::Biometric)->count();
        $kioskLogs = AttendanceLog::where('source', AttendanceSource::Kiosk)->count();
        $selfServiceLogs = AttendanceLog::where('source', AttendanceSource::SelfService)->count();

        expect($biometricLogs)->toBe(1);
        expect($kioskLogs)->toBe(1);
        expect($selfServiceLogs)->toBe(1);
    });
});
