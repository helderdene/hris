<?php

use App\Enums\TenantUserRole;
use App\Models\Candidate;
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

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
});

it('renders the candidate edit page', function () {
    $candidate = Candidate::factory()->create();

    $this->actingAs($this->user)
        ->get("{$this->baseUrl}/recruitment/candidates/{$candidate->id}/edit")
        ->assertSuccessful();
});

it('updates a candidate via API', function () {
    $candidate = Candidate::factory()->create([
        'first_name' => 'Original',
        'last_name' => 'Name',
        'email' => 'original@example.com',
    ]);

    $this->actingAs($this->user)
        ->putJson("{$this->baseUrl}/api/candidates/{$candidate->id}", [
            'first_name' => 'Updated',
            'last_name' => 'Person',
            'email' => 'updated@example.com',
        ])
        ->assertSuccessful();

    $candidate->refresh();
    expect($candidate->first_name)->toBe('Updated');
    expect($candidate->last_name)->toBe('Person');
    expect($candidate->email)->toBe('updated@example.com');
});
