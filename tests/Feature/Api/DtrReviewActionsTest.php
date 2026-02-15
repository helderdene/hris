<?php

use App\Enums\TenantUserRole;
use App\Models\DailyTimeRecord;
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

/*
|--------------------------------------------------------------------------
| Approve Overtime
|--------------------------------------------------------------------------
*/

it('approves overtime for a DTR record', function () {
    $record = DailyTimeRecord::factory()->withOvertime(60)->create();

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/approve-overtime");

    $response->assertSuccessful();
    $response->assertJsonFragment(['message' => 'Overtime approved successfully.']);

    $record->refresh();
    expect($record->overtime_approved)->toBeTrue();
});

it('returns error when approving overtime with zero minutes', function () {
    $record = DailyTimeRecord::factory()->create(['overtime_minutes' => 0]);

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/approve-overtime");

    $response->assertUnprocessable();
    $response->assertJsonFragment(['message' => 'No overtime to approve for this record.']);
});

/*
|--------------------------------------------------------------------------
| Deny Overtime
|--------------------------------------------------------------------------
*/

it('denies overtime for a DTR record', function () {
    $record = DailyTimeRecord::factory()->withOvertime(90)->create();

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/deny-overtime");

    $response->assertSuccessful();
    $response->assertJsonFragment(['message' => 'Overtime denied successfully.']);

    $record->refresh();
    expect($record->overtime_denied)->toBeTrue();
    expect($record->overtime_approved)->toBeFalse();
    expect($record->overtime_minutes)->toBe(90);
});

it('returns error when denying overtime with zero minutes', function () {
    $record = DailyTimeRecord::factory()->create(['overtime_minutes' => 0]);

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/deny-overtime");

    $response->assertUnprocessable();
    $response->assertJsonFragment(['message' => 'No overtime to deny for this record.']);
});

/*
|--------------------------------------------------------------------------
| Update Remarks
|--------------------------------------------------------------------------
*/

it('updates remarks for a DTR record', function () {
    $record = DailyTimeRecord::factory()->create(['remarks' => null]);

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/remarks", [
            'remarks' => 'Employee was on field duty.',
        ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['message' => 'Remarks updated successfully.']);

    $record->refresh();
    expect($record->remarks)->toBe('Employee was on field duty.');
});

it('clears remarks when set to null', function () {
    $record = DailyTimeRecord::factory()->create(['remarks' => 'Old remark']);

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/remarks", [
            'remarks' => null,
        ]);

    $response->assertSuccessful();

    $record->refresh();
    expect($record->remarks)->toBeNull();
});

it('rejects remarks exceeding max length', function () {
    $record = DailyTimeRecord::factory()->create();

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/remarks", [
            'remarks' => str_repeat('a', 1001),
        ]);

    $response->assertUnprocessable();
});

/*
|--------------------------------------------------------------------------
| Resolve Review
|--------------------------------------------------------------------------
*/

it('resolves review for a DTR record', function () {
    $record = DailyTimeRecord::factory()->needsReview('Missing time-out')->create();

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/resolve-review", [
            'remarks' => 'Verified with supervisor.',
        ]);

    $response->assertSuccessful();
    $response->assertJsonFragment(['message' => 'Review resolved successfully.']);

    $record->refresh();
    expect($record->needs_review)->toBeFalse();
    expect($record->review_reason)->toBeNull();
    expect($record->remarks)->toBe('Verified with supervisor.');
});

it('resolves review without remarks', function () {
    $record = DailyTimeRecord::factory()->needsReview()->create();

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/resolve-review");

    $response->assertSuccessful();

    $record->refresh();
    expect($record->needs_review)->toBeFalse();
});

it('returns error when resolving review on a record that does not need review', function () {
    $record = DailyTimeRecord::factory()->create(['needs_review' => false]);

    $response = $this->actingAs($this->user)
        ->postJson("{$this->baseUrl}/api/time-attendance/dtr/record/{$record->id}/resolve-review");

    $response->assertUnprocessable();
    $response->assertJsonFragment(['message' => 'This record does not need review.']);
});
