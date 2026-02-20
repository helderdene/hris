<?php

use App\Enums\TenantUserRole;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function createSelfServiceClockUser(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->withTrial()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
});

describe('Self-Service Clock-In', function () {
    it('clocks in when self-service is enabled', function () {
        $location = WorkLocation::factory()->create([
            'self_service_clockin_enabled' => true,
            'location_check' => 'none',
        ]);

        $user = createSelfServiceClockUser($this->tenant, TenantUserRole::Employee);
        $employee = Employee::factory()->create([
            'user_id' => $user->id,
            'work_location_id' => $location->id,
        ]);

        $this->actingAs($user);

        $response = $this->postJson("{$this->baseUrl}/api/clock", [
            'direction' => 'in',
        ]);

        $response->assertSuccessful();

        $log = AttendanceLog::where('employee_id', $employee->id)->first();
        expect($log)->not->toBeNull();
        expect($log->source->value)->toBe('self_service');
        expect($log->direction)->toBe('in');
    });

    it('rejects clock when self-service is disabled', function () {
        $location = WorkLocation::factory()->create([
            'self_service_clockin_enabled' => false,
        ]);

        $user = createSelfServiceClockUser($this->tenant, TenantUserRole::Employee);
        Employee::factory()->create([
            'user_id' => $user->id,
            'work_location_id' => $location->id,
        ]);

        $this->actingAs($user);

        $response = $this->postJson("{$this->baseUrl}/api/clock", [
            'direction' => 'in',
        ]);

        $response->assertForbidden();
    });

    it('returns clock status', function () {
        $location = WorkLocation::factory()->create([
            'self_service_clockin_enabled' => true,
            'location_check' => 'none',
        ]);

        $user = createSelfServiceClockUser($this->tenant, TenantUserRole::Employee);
        Employee::factory()->create([
            'user_id' => $user->id,
            'work_location_id' => $location->id,
        ]);

        $this->actingAs($user);

        $response = $this->getJson("{$this->baseUrl}/api/clock/status");

        $response->assertSuccessful();
        $response->assertJsonStructure(['self_service_enabled', 'location_check', 'suggested_direction']);
    });

    it('enforces cooldown between clock events', function () {
        $location = WorkLocation::factory()->create([
            'self_service_clockin_enabled' => true,
            'location_check' => 'none',
        ]);

        $user = createSelfServiceClockUser($this->tenant, TenantUserRole::Employee);
        Employee::factory()->create([
            'user_id' => $user->id,
            'work_location_id' => $location->id,
        ]);

        $this->actingAs($user);

        // First clock succeeds
        $this->postJson("{$this->baseUrl}/api/clock", ['direction' => 'in'])->assertSuccessful();

        // Second clock within cooldown fails
        $response = $this->postJson("{$this->baseUrl}/api/clock", ['direction' => 'out']);
        $response->assertUnprocessable();
    });
});
