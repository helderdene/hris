<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->withoutVite();

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
});

it('shows public card page for enabled employee', function () {
    $department = Department::factory()->create();
    $position = Position::factory()->create();

    $employee = Employee::factory()->withBusinessCard()->create([
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $response = $this->get("{$this->baseUrl}/card/{$employee->business_card_token}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('BusinessCard/Show')
        ->where('employee.full_name', $employee->full_name)
        ->where('employee.email', $employee->email)
        ->where('employee.position', $position->title)
        ->where('employee.department', $department->name)
    );
});

it('passes company info and work location to business card page', function () {
    $this->tenant->update([
        'business_info' => [
            'website' => 'https://example.com',
            'linkedin' => 'https://linkedin.com/company/example',
            'facebook' => 'https://facebook.com/example',
            'instagram' => 'https://instagram.com/example',
            'twitter' => 'https://x.com/example',
        ],
    ]);

    $workLocation = WorkLocation::factory()->create([
        'name' => 'Main Office',
        'address' => '123 Main St',
        'city' => 'Manila',
        'region' => 'NCR',
        'country' => 'PH',
    ]);

    $employee = Employee::factory()->withBusinessCard()->create([
        'work_location_id' => $workLocation->id,
    ]);

    $response = $this->get("{$this->baseUrl}/card/{$employee->business_card_token}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('BusinessCard/Show')
        ->where('employee.work_location.name', 'Main Office')
        ->where('employee.work_location.address', '123 Main St')
        ->where('employee.work_location.city', 'Manila')
        ->where('company.website', 'https://example.com')
        ->where('company.linkedin', 'https://linkedin.com/company/example')
        ->where('company.facebook', 'https://facebook.com/example')
    );
});

it('includes address and url in vcard when available', function () {
    $this->tenant->update([
        'business_info' => [
            'website' => 'https://example.com',
        ],
    ]);

    $workLocation = WorkLocation::factory()->create([
        'address' => '456 Office Blvd',
        'city' => 'Cebu',
        'region' => 'Cebu',
        'country' => 'PH',
    ]);

    $employee = Employee::factory()->withBusinessCard()->create([
        'first_name' => 'Maria',
        'middle_name' => null,
        'last_name' => 'Santos',
        'suffix' => null,
        'work_location_id' => $workLocation->id,
    ]);

    $response = $this->get("{$this->baseUrl}/card/{$employee->business_card_token}/vcard");

    $response->assertSuccessful();
    $content = $response->getContent();
    expect($content)->toContain('ADR;TYPE=WORK:;;456 Office Blvd;Cebu;Cebu;;PH');
    expect($content)->toContain('URL:https://example.com');
});

it('returns 404 for disabled card', function () {
    $employee = Employee::factory()->create([
        'business_card_token' => fake()->uuid(),
        'business_card_enabled' => false,
    ]);

    $response = $this->get("{$this->baseUrl}/card/{$employee->business_card_token}");

    $response->assertNotFound();
});

it('returns 404 for invalid token', function () {
    $response = $this->get("{$this->baseUrl}/card/non-existent-token");

    $response->assertNotFound();
});

it('returns 404 for terminated employee', function () {
    $employee = Employee::factory()->terminated()->withBusinessCard()->create();

    $response = $this->get("{$this->baseUrl}/card/{$employee->business_card_token}");

    $response->assertNotFound();
});

it('downloads vcard with correct content-type and fields', function () {
    $department = Department::factory()->create();
    $position = Position::factory()->create(['title' => 'Software Engineer']);

    $employee = Employee::factory()->withBusinessCard()->create([
        'first_name' => 'Juan',
        'middle_name' => null,
        'last_name' => 'Dela Cruz',
        'suffix' => null,
        'email' => 'juan@example.com',
        'phone' => '+63 912 345 6789',
        'department_id' => $department->id,
        'position_id' => $position->id,
    ]);

    $response = $this->get("{$this->baseUrl}/card/{$employee->business_card_token}/vcard");

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/vcard; charset=utf-8');

    $content = $response->getContent();
    $escapedOrg = str_replace([',', ';'], ['\\,', '\\;'], $this->tenant->name);
    expect($content)->toContain('BEGIN:VCARD');
    expect($content)->toContain('VERSION:3.0');
    expect($content)->toContain('FN:Juan Dela Cruz');
    expect($content)->toContain("ORG:{$escapedOrg}");
    expect($content)->toContain('TITLE:Software Engineer');
    expect($content)->toContain('TEL;TYPE=WORK:+63 912 345 6789');
    expect($content)->toContain('EMAIL;TYPE=WORK:juan@example.com');
    expect($content)->toContain('END:VCARD');
});

it('does not require authentication for public card page', function () {
    $employee = Employee::factory()->withBusinessCard()->create();

    $response = $this->get("{$this->baseUrl}/card/{$employee->business_card_token}");

    $response->assertSuccessful();
});

it('allows admin to toggle card on', function () {
    $user = User::factory()->create();
    $this->tenant->users()->attach($user, ['role' => 'admin']);
    $this->actingAs($user);

    $employee = Employee::factory()->create([
        'business_card_enabled' => false,
        'business_card_token' => null,
    ]);

    $response = $this->postJson("{$this->baseUrl}/api/employees/{$employee->id}/business-card/toggle");

    $response->assertSuccessful();
    $response->assertJson([
        'business_card_enabled' => true,
    ]);

    $employee->refresh();
    expect($employee->business_card_enabled)->toBeTrue();
    expect($employee->business_card_token)->not->toBeNull();
});

it('allows admin to toggle card off', function () {
    $user = User::factory()->create();
    $this->tenant->users()->attach($user, ['role' => 'admin']);
    $this->actingAs($user);

    $employee = Employee::factory()->withBusinessCard()->create();

    $response = $this->postJson("{$this->baseUrl}/api/employees/{$employee->id}/business-card/toggle");

    $response->assertSuccessful();
    $response->assertJson([
        'business_card_enabled' => false,
    ]);
});

it('returns 403 for non-admin on toggle', function () {
    $user = User::factory()->create();
    $this->tenant->users()->attach($user, ['role' => 'employee']);
    $this->actingAs($user);

    $employee = Employee::factory()->create();

    $response = $this->postJson("{$this->baseUrl}/api/employees/{$employee->id}/business-card/toggle");

    $response->assertForbidden();
});

it('generates UUID token on first enable', function () {
    $user = User::factory()->create();
    $this->tenant->users()->attach($user, ['role' => 'admin']);
    $this->actingAs($user);

    $employee = Employee::factory()->create([
        'business_card_token' => null,
        'business_card_enabled' => false,
    ]);

    expect($employee->business_card_token)->toBeNull();

    $this->postJson("{$this->baseUrl}/api/employees/{$employee->id}/business-card/toggle");

    $employee->refresh();
    expect($employee->business_card_token)->not->toBeNull();
    expect(\Illuminate\Support\Str::isUuid($employee->business_card_token))->toBeTrue();
});
