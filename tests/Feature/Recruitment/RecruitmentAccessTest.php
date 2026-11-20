<?php

use App\Enums\EmploymentStatus;
use App\Enums\TenantUserRole;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\InterviewPanelist;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\Offer;
use App\Models\OnboardingChecklistItem;
use App\Models\PreboardingChecklist;
use App\Models\PreboardingChecklistItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);
    Notification::fake();

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // A lifetime tenant on no plan would fail the module check, so keep it on trial.
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addMonth()]);
    app()->instance('tenant', $tenant);
    $this->base = "http://{$tenant->slug}.kasamahr.test";

    $this->userWithRole = function (TenantUserRole $role, array $attributes = []) use ($tenant): User {
        $user = User::factory()->create($attributes);
        $user->tenants()->attach($tenant->id, ['role' => $role->value, 'invited_at' => now(), 'invitation_accepted_at' => now()]);

        return $user;
    };

    $this->employeeWithRole = fn (TenantUserRole $role) => Employee::factory()->create([
        'employment_status' => EmploymentStatus::Active,
        'user_id' => ($this->userWithRole)($role)->id,
    ]);

    $this->as = fn (Employee|User $who) => $this->actingAs($who instanceof User ? $who : User::find($who->user_id));

    $this->application = JobApplication::factory()->create();
    $this->posting = JobPosting::factory()->create();
    $this->offer = Offer::factory()->create();
});

it('keeps plain employees out of recruitment pages and data', function (string $method, string $path) {
    ($this->as)(($this->userWithRole)(TenantUserRole::Employee));

    $url = $this->base.str_replace(
        ['{candidate}', '{application}', '{posting}', '{offer}'],
        [$this->application->candidate_id, $this->application->id, $this->posting->id, $this->offer->id],
        $path
    );

    $this->json($method, $url)->assertForbidden();
})->with([
    ['GET', '/recruitment/candidates'],
    ['GET', '/recruitment/candidates/{candidate}'],
    ['GET', '/recruitment/applications/{application}'],
    ['GET', '/recruitment/offers'],
    ['GET', '/recruitment/offers/{offer}'],
    ['GET', '/recruitment/job-postings'],
    ['GET', '/recruitment/interviews'],
    ['GET', '/api/candidates/{candidate}'],
    ['POST', '/api/candidates/check-duplicates'],
    ['GET', '/api/applications/{application}'],
    ['PATCH', '/api/applications/{application}/status'],
    ['GET', '/api/offers/{offer}'],
    ['GET', '/api/offers/{offer}/pdf'],
    ['POST', '/api/job-postings'],
    ['PUT', '/api/job-postings/{posting}'],
    ['POST', '/api/job-postings/{posting}/publish'],
    ['POST', '/api/job-postings/{posting}/close'],
    ['DELETE', '/api/job-postings/{posting}'],
]);

it('lets a supervisor view recruitment data but not change it', function () {
    ($this->as)(($this->userWithRole)(TenantUserRole::Supervisor));

    $this->get("{$this->base}/recruitment/candidates")->assertOk();
    $this->getJson("{$this->base}/api/candidates/{$this->application->candidate_id}")->assertOk();
    $this->getJson("{$this->base}/api/applications/{$this->application->id}")->assertOk();

    $this->postJson("{$this->base}/api/job-postings/{$this->posting->id}/publish")->assertForbidden();
    $this->patchJson("{$this->base}/api/applications/{$this->application->id}/status", ['status' => 'rejected'])->assertForbidden();
    $this->deleteJson("{$this->base}/api/job-postings/{$this->posting->id}")->assertForbidden();
});

it('lets HR roles manage recruitment', function (TenantUserRole $role) {
    ($this->as)(($this->userWithRole)($role));

    $this->get("{$this->base}/recruitment/candidates")->assertOk();

    expect($this->postJson("{$this->base}/api/job-postings/{$this->posting->id}/publish")->status())->not->toBe(403)
        ->and($this->patchJson("{$this->base}/api/applications/{$this->application->id}/status", [])->status())->not->toBe(403);
})->with([TenantUserRole::Admin, TenantUserRole::HrManager, TenantUserRole::HrStaff, TenantUserRole::HrConsultant]);

it('lets an interview panelist reach their interview, and only theirs', function () {
    $panelist = ($this->employeeWithRole)(TenantUserRole::Employee);
    $outsider = ($this->employeeWithRole)(TenantUserRole::Employee);
    $interview = Interview::factory()->create(['job_application_id' => $this->application->id]);
    InterviewPanelist::factory()->create(['interview_id' => $interview->id, 'employee_id' => $panelist->id]);

    $paths = [
        "/recruitment/interviews/{$interview->id}",
        "/api/interviews/{$interview->id}/feedback",
        "/api/interviews/{$interview->id}/calendar.ics",
    ];

    ($this->as)($panelist);
    foreach ($paths as $path) {
        $this->get($this->base.$path)->assertOk();
    }
    expect($this->postJson("{$this->base}/api/interviews/{$interview->id}/feedback", [])->status())->not->toBe(403);

    ($this->as)($outsider);
    foreach ($paths as $path) {
        $this->get($this->base.$path)->assertForbidden();
    }
    $this->postJson("{$this->base}/api/interviews/{$interview->id}/feedback", ['feedback' => 'x', 'rating' => 3])->assertForbidden();
});

it('leaves job requisitions readable by all staff, as the requisitions page is designed', function () {
    $requisition = JobRequisition::factory()->create();

    ($this->as)(($this->userWithRole)(TenantUserRole::Employee));
    $this->get("{$this->base}/recruitment/requisitions")->assertOk();
    $this->get("{$this->base}/recruitment/requisitions/{$requisition->id}")->assertOk();
    $this->get("{$this->base}/recruitment/approvals")->assertOk();
});

it('limits onboarding tasks to HR and the person they are assigned to', function () {
    $assignee = ($this->userWithRole)(TenantUserRole::Employee);
    $item = OnboardingChecklistItem::factory()->create(['assigned_to' => $assignee->id]);
    $api = "{$this->base}/api/onboarding-items/{$item->id}";

    ($this->as)(($this->userWithRole)(TenantUserRole::Employee));
    $this->postJson("{$api}/start")->assertForbidden();
    $this->postJson("{$api}/complete")->assertForbidden();
    $this->postJson("{$api}/skip", ['reason' => 'n/a'])->assertForbidden();
    $this->postJson("{$api}/assign", ['assigned_to' => $assignee->id])->assertForbidden();

    ($this->as)($assignee);
    $this->postJson("{$api}/assign", ['assigned_to' => $assignee->id])->assertForbidden();
    expect($this->postJson("{$api}/start")->status())->not->toBe(403);

    ($this->as)(($this->userWithRole)(TenantUserRole::HrManager));
    expect($this->postJson("{$api}/assign", ['assigned_to' => $assignee->id])->status())->not->toBe(403);
});

it('limits preboarding submissions to the new hire and HR', function () {
    $candidate = Candidate::factory()->create(['email' => 'newhire@example.com']);
    $checklist = PreboardingChecklist::factory()->create([
        'job_application_id' => JobApplication::factory()->create(['candidate_id' => $candidate->id])->id,
    ]);
    $item = PreboardingChecklistItem::factory()->create(['preboarding_checklist_id' => $checklist->id]);
    $url = "{$this->base}/api/preboarding-items/{$item->id}/submit";

    ($this->as)(($this->userWithRole)(TenantUserRole::Employee));
    $this->postJson($url)->assertForbidden();

    ($this->as)(($this->userWithRole)(TenantUserRole::Employee, ['email' => 'NewHire@example.com']));
    expect($this->postJson($url)->status())->not->toBe(403);

    ($this->as)(($this->userWithRole)(TenantUserRole::HrManager));
    expect($this->postJson($url)->status())->not->toBe(403);
});
