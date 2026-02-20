<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Kiosk\KioskPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('KioskPinService', function () {
    it('generates a PIN of the specified length', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $service = app(KioskPinService::class);
        $pin = $service->generatePin(4);

        expect($pin)->toHaveLength(4);
        expect($pin)->toMatch('/^\d{4}$/');
    });

    it('generates a 6-digit PIN when length is 6', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $service = app(KioskPinService::class);
        $pin = $service->generatePin(6);

        expect($pin)->toHaveLength(6);
        expect($pin)->toMatch('/^\d{6}$/');
    });

    it('assigns PIN to employee and stores hashes', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $employee = Employee::factory()->create();
        $service = app(KioskPinService::class);
        $pin = $service->assignPin($employee);

        $employee->refresh();

        expect($pin)->toHaveLength(4);
        expect($employee->kiosk_pin)->not->toBeNull(); // bcrypt hash
        expect($employee->kiosk_pin_hash)->not->toBeNull(); // SHA-256 lookup hash
        expect($employee->kiosk_pin_changed_at)->not->toBeNull();
    });

    it('verifies correct PIN returns employee', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $employee = Employee::factory()->create();
        $service = app(KioskPinService::class);
        $pin = $service->assignPin($employee);

        $found = $service->verifyPin($pin);

        expect($found)->not->toBeNull();
        expect($found->id)->toBe($employee->id);
    });

    it('verifyPin returns null for invalid PIN', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $service = app(KioskPinService::class);

        expect($service->verifyPin('9999'))->toBeNull();
    });

    it('resets PIN and old PIN no longer works', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $employee = Employee::factory()->create();
        $service = app(KioskPinService::class);
        $oldPin = $service->assignPin($employee);
        $newPin = $service->resetPin($employee);

        expect($newPin)->not->toBe($oldPin);
        expect($service->verifyPin($oldPin))->toBeNull();
        expect($service->verifyPin($newPin)->id)->toBe($employee->id);
    });

    it('generates unique PINs for different employees', function () {
        $tenant = Tenant::factory()->create();
        app()->instance('tenant', $tenant);

        $service = app(KioskPinService::class);
        $pins = [];

        // Create 10 employees with PINs - they should all be unique
        for ($i = 0; $i < 10; $i++) {
            $employee = Employee::factory()->create();
            $pins[] = $service->assignPin($employee);
        }

        expect(count(array_unique($pins)))->toBe(10);
    });
});
