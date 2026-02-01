<?php

use App\Enums\TenantUserRole;
use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Models\CandidateWorkExperience;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;

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
});

describe('Candidate Model', function () {
    it('computes full name', function () {
        $candidate = Candidate::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        expect($candidate->full_name)->toBe('John Doe');
    });

    it('has education relationship', function () {
        $candidate = Candidate::factory()->create();
        CandidateEducation::factory()->count(2)->for($candidate)->create();

        expect($candidate->education)->toHaveCount(2);
    });

    it('has work experiences relationship', function () {
        $candidate = Candidate::factory()->create();
        CandidateWorkExperience::factory()->count(3)->for($candidate)->create();

        expect($candidate->workExperiences)->toHaveCount(3);
    });

    it('searches by name or email', function () {
        Candidate::factory()->create(['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com']);
        Candidate::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com']);

        expect(Candidate::searchByNameOrEmail('John')->count())->toBe(1);
        expect(Candidate::searchByNameOrEmail('jane@example.com')->count())->toBe(1);
        expect(Candidate::searchByNameOrEmail('nonexistent')->count())->toBe(0);
    });

    it('casts skills as array', function () {
        $candidate = Candidate::factory()->create(['skills' => ['PHP', 'Laravel']]);

        expect($candidate->skills)->toBeArray();
        expect($candidate->skills)->toContain('PHP');
    });
});

describe('Candidate API', function () {
    it('lists candidates with search', function () {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        Gate::define('can-manage-organization', fn () => true);

        Candidate::factory()->create(['first_name' => 'John', 'last_name' => 'Doe']);
        Candidate::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith']);

        $this->actingAs($user)
            ->getJson("{$this->baseUrl}/api/candidates?search=John")
            ->assertSuccessful()
            ->assertJsonCount(1, 'data');
    });

    it('creates a candidate via API', function () {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        Gate::define('can-manage-organization', fn () => true);

        $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/candidates", [
                'first_name' => 'New',
                'last_name' => 'Candidate',
                'email' => 'new@example.com',
                'phone' => '+639171234567',
            ])
            ->assertCreated()
            ->assertJsonPath('full_name', 'New Candidate');

        expect(Candidate::where('email', 'new@example.com')->exists())->toBeTrue();
    });

    it('checks for duplicates', function () {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        Gate::define('can-manage-organization', fn () => true);

        Candidate::factory()->create(['email' => 'existing@example.com']);

        $this->actingAs($user)
            ->postJson("{$this->baseUrl}/api/candidates/check-duplicates", [
                'email' => 'existing@example.com',
            ])
            ->assertSuccessful()
            ->assertJsonPath('has_duplicates', true);
    });

    it('deletes a candidate', function () {
        $user = User::factory()->create();
        $user->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
        Gate::define('can-manage-organization', fn () => true);

        $candidate = Candidate::factory()->create();

        $this->actingAs($user)
            ->deleteJson("{$this->baseUrl}/api/candidates/{$candidate->id}")
            ->assertSuccessful();

        expect(Candidate::find($candidate->id))->toBeNull();
    });
});
