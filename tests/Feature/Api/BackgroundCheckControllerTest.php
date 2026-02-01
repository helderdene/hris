<?php

use App\Enums\ApplicationStatus;
use App\Enums\BackgroundCheckStatus;
use App\Enums\TenantUserRole;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use App\Models\JobApplication;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);
    Gate::define('can-manage-organization', fn () => true);
});

it('can list background checks for an application', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();
    BackgroundCheck::factory()->count(2)->create(['job_application_id' => $application->id]);

    $this->actingAs($this->user)
        ->getJson("{$this->baseUrl}/api/applications/{$application->id}/background-checks")
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

it('can create a background check at offer stage', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/background-checks", [
            'check_type' => 'Criminal',
            'status' => BackgroundCheckStatus::Pending->value,
            'provider' => 'CheckPro Inc.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.check_type', 'Criminal')
        ->assertJsonPath('data.status', 'pending');
});

it('blocks background check creation before offer stage', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Assessment)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/background-checks", [
            'check_type' => 'Criminal',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

it('can update a background check', function () {
    $check = BackgroundCheck::factory()->create();

    $this->actingAs($this->user)
        ->putJson("{$this->baseUrl}/api/background-checks/{$check->id}", [
            'status' => BackgroundCheckStatus::InProgress->value,
            'started_at' => now()->format('Y-m-d'),
        ])
        ->assertSuccessful()
        ->assertJsonPath('data.status', 'in_progress');
});

it('can delete a background check', function () {
    $check = BackgroundCheck::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson("{$this->baseUrl}/api/background-checks/{$check->id}")
        ->assertNoContent();

    expect(BackgroundCheck::find($check->id))->toBeNull();
});

it('can upload a document to a background check', function () {
    Storage::fake('local');

    $check = BackgroundCheck::factory()->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/background-checks/{$check->id}/documents", [
            'file' => UploadedFile::fake()->create('report.pdf', 500, 'application/pdf'),
        ])
        ->assertCreated()
        ->assertJsonPath('data.file_name', 'report.pdf');

    expect(BackgroundCheckDocument::count())->toBe(1);
});

it('can delete a background check document', function () {
    Storage::fake('local');

    $doc = BackgroundCheckDocument::factory()->create();

    $this->actingAs($this->user)
        ->deleteJson("{$this->baseUrl}/api/background-check-documents/{$doc->id}")
        ->assertNoContent();

    expect(BackgroundCheckDocument::find($doc->id))->toBeNull();
});

it('validates required fields when creating a background check', function () {
    $application = JobApplication::factory()->withStatus(ApplicationStatus::Offer)->create();

    $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/applications/{$application->id}/background-checks", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['check_type']);
});
