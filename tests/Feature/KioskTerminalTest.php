<?php

use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Tenant;
use App\Services\Kiosk\KioskPinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->withTrial()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
    $this->withoutVite();
});

describe('Kiosk Terminal', function () {
    it('shows kiosk page with valid token', function () {
        $kiosk = Kiosk::factory()->active()->create();

        $response = $this->get("{$this->baseUrl}/kiosk/{$kiosk->token}");

        $response->assertSuccessful();
    });

    it('returns 404 for invalid token', function () {
        $response = $this->get("{$this->baseUrl}/kiosk/invalid-token-that-does-not-exist");

        $response->assertNotFound();
    });

    it('returns 404 for inactive kiosk', function () {
        $kiosk = Kiosk::factory()->inactive()->create();

        $response = $this->get("{$this->baseUrl}/kiosk/{$kiosk->token}");

        $response->assertNotFound();
    });

    it('blocks access when IP is not in whitelist', function () {
        $kiosk = Kiosk::factory()->active()->withIpWhitelist(['10.0.0.0/8'])->create();

        // Default test IP is 127.0.0.1, not in 10.0.0.0/8
        $response = $this->get("{$this->baseUrl}/kiosk/{$kiosk->token}");

        $response->assertForbidden();
    });

    it('verifies PIN and returns employee info', function () {
        $kiosk = Kiosk::factory()->active()->create();
        $employee = Employee::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'middle_name' => null,
            'suffix' => null,
        ]);

        $pinService = app(KioskPinService::class);
        $pin = $pinService->assignPin($employee);

        $response = $this->postJson("{$this->baseUrl}/kiosk/{$kiosk->token}/verify-pin", [
            'pin' => $pin,
        ]);

        $response->assertSuccessful();
        $response->assertJsonPath('employee.name', 'John Doe');
    });

    it('rejects invalid PIN', function () {
        $kiosk = Kiosk::factory()->active()->create();

        $response = $this->postJson("{$this->baseUrl}/kiosk/{$kiosk->token}/verify-pin", [
            'pin' => '0000',
        ]);

        $response->assertUnprocessable();
    });

    it('clocks in employee via kiosk', function () {
        $kiosk = Kiosk::factory()->active()->create();
        $employee = Employee::factory()->create();

        $response = $this->postJson("{$this->baseUrl}/kiosk/{$kiosk->token}/clock", [
            'employee_id' => $employee->id,
            'direction' => 'in',
        ]);

        $response->assertSuccessful();

        $log = AttendanceLog::where('employee_id', $employee->id)->first();
        expect($log)->not->toBeNull();
        expect($log->source->value)->toBe('kiosk');
        expect($log->kiosk_id)->toBe($kiosk->id);
        expect($log->direction)->toBe('in');
    });

    it('enforces cooldown period', function () {
        $kiosk = Kiosk::factory()->active()->create(['settings' => ['cooldown_minutes' => 5]]);
        $employee = Employee::factory()->create();

        // First clock should succeed
        $this->postJson("{$this->baseUrl}/kiosk/{$kiosk->token}/clock", [
            'employee_id' => $employee->id,
            'direction' => 'in',
        ])->assertSuccessful();

        // Second clock within cooldown should fail
        $response = $this->postJson("{$this->baseUrl}/kiosk/{$kiosk->token}/clock", [
            'employee_id' => $employee->id,
            'direction' => 'out',
        ]);

        $response->assertUnprocessable();
    });
});
