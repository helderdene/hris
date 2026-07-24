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
use Illuminate\Support\Facades\Storage;

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

        Candidate::factory()->create(['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john.doe@example.com']);
        Candidate::factory()->create(['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane.smith@example.com']);

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

describe('Resume Download', function () {
    beforeEach(function () {
        Storage::fake('local');

        $this->user = User::factory()->create();
        $this->user->tenants()->attach($this->tenant->id, [
            'role' => TenantUserRole::HrManager->value,
            'invited_at' => now(),
            'invitation_accepted_at' => now(),
        ]);
    });

    it('downloads the resume with its original filename', function () {
        Storage::disk('local')->put('resumes/stored-file.pdf', 'fake pdf content');
        Gate::define('can-manage-organization', fn () => true);

        $candidate = Candidate::factory()->create([
            'resume_file_path' => 'resumes/stored-file.pdf',
            'resume_file_name' => 'john-doe-resume.pdf',
        ]);

        $this->actingAs($this->user)
            ->get("{$this->baseUrl}/api/candidates/{$candidate->id}/resume")
            ->assertSuccessful()
            ->assertDownload('john-doe-resume.pdf');
    });

    it('falls back to the stored filename when the original name is missing', function () {
        Storage::disk('local')->put('resumes/stored-file.pdf', 'fake pdf content');
        Gate::define('can-manage-organization', fn () => true);

        $candidate = Candidate::factory()->create([
            'resume_file_path' => 'resumes/stored-file.pdf',
            'resume_file_name' => null,
        ]);

        $this->actingAs($this->user)
            ->get("{$this->baseUrl}/api/candidates/{$candidate->id}/resume")
            ->assertSuccessful()
            ->assertDownload('stored-file.pdf');
    });

    it('returns 404 when the candidate has no resume', function () {
        Gate::define('can-manage-organization', fn () => true);

        $candidate = Candidate::factory()->create([
            'resume_file_path' => null,
            'resume_file_name' => null,
        ]);

        $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/api/candidates/{$candidate->id}/resume")
            ->assertNotFound();
    });

    it('returns 404 when the resume file is missing from storage', function () {
        Gate::define('can-manage-organization', fn () => true);

        $candidate = Candidate::factory()->create([
            'resume_file_path' => 'resumes/deleted-file.pdf',
            'resume_file_name' => 'john-doe-resume.pdf',
        ]);

        $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/api/candidates/{$candidate->id}/resume")
            ->assertNotFound();
    });

    it('forbids users without organization management permission', function () {
        Storage::disk('local')->put('resumes/stored-file.pdf', 'fake pdf content');
        Gate::define('can-manage-organization', fn () => false);

        $candidate = Candidate::factory()->create([
            'resume_file_path' => 'resumes/stored-file.pdf',
            'resume_file_name' => 'john-doe-resume.pdf',
        ]);

        $this->actingAs($this->user)
            ->getJson("{$this->baseUrl}/api/candidates/{$candidate->id}/resume")
            ->assertForbidden();
    });
});
