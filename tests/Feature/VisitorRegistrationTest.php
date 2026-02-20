<?php

use App\Enums\VisitStatus;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\Visitor;
use App\Models\VisitorVisit;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

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

describe('Public Visitor Registration', function () {
    it('shows the public registration page', function () {
        $response = $this->get("{$this->baseUrl}/visit/register");

        $response->assertSuccessful();
    });

    it('registers a new visitor', function () {
        Notification::fake();

        $location = WorkLocation::factory()->active()->create();
        $employee = Employee::factory()->create();

        $response = $this->post("{$this->baseUrl}/visit/register", [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
            'work_location_id' => $location->id,
            'host_employee_id' => $employee->id,
            'purpose' => 'Meeting with team lead',
            'expected_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('visitors', [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
        ]);

        $visitor = Visitor::where('email', 'juan@example.com')->first();
        expect($visitor)->not->toBeNull();

        $visit = VisitorVisit::where('visitor_id', $visitor->id)->first();
        expect($visit)->not->toBeNull();
        expect($visit->status)->toBe(VisitStatus::PendingApproval);
        expect($visit->work_location_id)->toBe($location->id);
        expect($visit->host_employee_id)->toBe($employee->id);
        expect($visit->purpose)->toBe('Meeting with team lead');
    });

    it('reuses existing visitor by email', function () {
        Notification::fake();

        $location = WorkLocation::factory()->active()->create();
        $employee = Employee::factory()->create();

        $existingVisitor = Visitor::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
        ]);

        $response = $this->post("{$this->baseUrl}/visit/register", [
            'first_name' => 'Juan Updated',
            'last_name' => 'Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09179999999',
            'work_location_id' => $location->id,
            'host_employee_id' => $employee->id,
            'purpose' => 'Follow-up meeting',
            'expected_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect();

        expect(Visitor::where('email', 'juan@example.com')->count())->toBe(1);

        $visit = VisitorVisit::where('visitor_id', $existingVisitor->id)->first();
        expect($visit)->not->toBeNull();
    });

    it('validates required fields', function () {
        $response = $this->post("{$this->baseUrl}/visit/register", []);

        $response->assertSessionHasErrors([
            'first_name',
            'last_name',
            'email',
            'purpose',
            'work_location_id',
            'host_employee_id',
            'expected_at',
        ]);
    });

    it('validates email format', function () {
        $response = $this->post("{$this->baseUrl}/visit/register", [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'not-a-valid-email',
            'purpose' => 'Meeting',
            'work_location_id' => 1,
            'host_employee_id' => 1,
            'expected_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors(['email']);
    });
});
