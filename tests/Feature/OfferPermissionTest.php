<?php

use App\Enums\TenantUserRole;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";

    $this->posting = JobPosting::factory()->published()->create();
    $this->application = JobApplication::factory()->for($this->posting, 'jobPosting')->create();

    $this->offerPayload = [
        'job_application_id' => null,
        'salary' => 50000,
        'salary_currency' => 'PHP',
        'salary_frequency' => 'monthly',
        'start_date' => now()->addDays(30)->toDateString(),
        'expiry_date' => now()->addDays(14)->toDateString(),
        'position_title' => 'Software Engineer',
        'employment_type' => 'full_time',
    ];
    $this->offerPayload['job_application_id'] = $this->application->id;
});

it('allows HR manager to create offers', function () {
    $user = User::factory()->create();
    $user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson("{$this->baseUrl}/api/offers", $this->offerPayload)
        ->assertRedirect();
});

it('allows admin to create offers', function () {
    $user = User::factory()->create();
    $user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Admin->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson("{$this->baseUrl}/api/offers", $this->offerPayload)
        ->assertRedirect();
});

it('denies employee from creating offers', function () {
    $user = User::factory()->create();
    $user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::Employee->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson("{$this->baseUrl}/api/offers", $this->offerPayload)
        ->assertForbidden();
});
