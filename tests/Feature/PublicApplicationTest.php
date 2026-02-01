<?php

use App\Models\Candidate;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
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

describe('Public Application', function () {
    it('submits application via careers page', function () {
        $posting = JobPosting::factory()->published()->create();

        $this->post("{$this->baseUrl}/careers/{$posting->slug}/apply", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+639171234567',
            'cover_letter' => 'I am interested in this position.',
        ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Your application has been submitted successfully.');

        expect(Candidate::where('email', 'john@example.com')->exists())->toBeTrue();
        expect(JobApplication::where('job_posting_id', $posting->id)->exists())->toBeTrue();
    });

    it('validates required fields', function () {
        $posting = JobPosting::factory()->published()->create();

        $this->postJson("{$this->baseUrl}/careers/{$posting->slug}/apply", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
    });

    it('rejects invalid file types', function () {
        $posting = JobPosting::factory()->published()->create();

        $this->postJson("{$this->baseUrl}/careers/{$posting->slug}/apply", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'resume' => UploadedFile::fake()->create('virus.exe', 100),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['resume']);
    });

    it('fails for non-published posting', function () {
        $posting = JobPosting::factory()->draft()->create();

        $this->post("{$this->baseUrl}/careers/{$posting->slug}/apply", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ])
            ->assertNotFound();
    });

    it('reuses existing candidate by email', function () {
        $posting = JobPosting::factory()->published()->create();
        $existing = Candidate::factory()->create(['email' => 'existing@example.com']);

        $this->post("{$this->baseUrl}/careers/{$posting->slug}/apply", [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'existing@example.com',
        ])
            ->assertRedirect();

        expect(Candidate::where('email', 'existing@example.com')->count())->toBe(1);
        expect(JobApplication::where('candidate_id', $existing->id)->exists())->toBeTrue();
    });

    it('handles resume upload', function () {
        Storage::fake('local');
        $posting = JobPosting::factory()->published()->create();

        $this->post("{$this->baseUrl}/careers/{$posting->slug}/apply", [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'resume' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])
            ->assertRedirect();

        $candidate = Candidate::where('email', 'john@example.com')->first();
        expect($candidate->resume_file_name)->toBe('resume.pdf');
        expect($candidate->resume_file_path)->not->toBeNull();
    });
});
