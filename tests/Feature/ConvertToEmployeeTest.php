<?php

use App\Actions\ConvertToEmployeeAction;
use App\Enums\ApplicationStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OfferStatus;
use App\Enums\PreboardingStatus;
use App\Enums\TenantUserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Models\Position;
use App\Models\PreboardingChecklist;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);

    URL::defaults(['tenant' => $this->tenant->slug]);

    $this->user = User::factory()->create();
    $this->user->tenants()->attach($this->tenant->id, [
        'role' => TenantUserRole::HrManager->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    Gate::define('can-manage-employees', fn () => true);
    Gate::define('can-manage-organization', fn () => true);
});

describe('ConvertToEmployeeAction', function () {
    it('creates an employee from a completed preboarding checklist', function () {
        $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
        $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
            'job_application_id' => $application->id,
            'salary' => 50000,
            'salary_frequency' => 'monthly',
            'start_date' => now()->addDays(14),
            'position_title' => 'Software Engineer',
            'employment_type' => 'full_time',
        ]);
        $checklist = PreboardingChecklist::factory()->create([
            'job_application_id' => $application->id,
            'offer_id' => $offer->id,
            'status' => PreboardingStatus::Completed,
            'completed_at' => now(),
        ]);

        // Create a user for the candidate
        User::factory()->create(['email' => $application->candidate->email]);

        $action = new ConvertToEmployeeAction;
        $employee = $action->execute($checklist);

        expect($employee)->toBeInstanceOf(Employee::class);
        expect($employee->first_name)->toBe($application->candidate->first_name);
        expect($employee->last_name)->toBe($application->candidate->last_name);
        expect($employee->email)->toBe($application->candidate->email);
        expect($employee->basic_salary)->toBe('50000.00');
        expect($employee->hire_date->toDateString())->toBe($offer->start_date->toDateString());
        expect($employee->employment_status)->toBe(EmploymentStatus::Active);
    });

    it('links the employee to the user account', function () {
        $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
        $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
            'job_application_id' => $application->id,
        ]);
        $checklist = PreboardingChecklist::factory()->create([
            'job_application_id' => $application->id,
            'offer_id' => $offer->id,
            'status' => PreboardingStatus::Completed,
        ]);

        $candidateUser = User::factory()->create(['email' => $application->candidate->email]);

        $action = new ConvertToEmployeeAction;
        $employee = $action->execute($checklist);

        expect($employee->user_id)->toBe($candidateUser->id);
    });

    it('generates a unique employee number', function () {
        $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
        $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
            'job_application_id' => $application->id,
        ]);
        $checklist = PreboardingChecklist::factory()->create([
            'job_application_id' => $application->id,
            'offer_id' => $offer->id,
            'status' => PreboardingStatus::Completed,
        ]);

        $action = new ConvertToEmployeeAction;
        $employee = $action->execute($checklist);

        expect($employee->employee_number)->toMatch('/^EMP-\d{4}-\d{4}$/');
    });

    it('finds matching department and position', function () {
        $department = Department::factory()->create(['name' => 'Engineering']);
        $position = Position::factory()->create(['title' => 'Software Engineer']);

        $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
        $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
            'job_application_id' => $application->id,
            'department' => 'Engineering',
            'position_title' => 'Software Engineer',
        ]);
        $checklist = PreboardingChecklist::factory()->create([
            'job_application_id' => $application->id,
            'offer_id' => $offer->id,
            'status' => PreboardingStatus::Completed,
        ]);

        $action = new ConvertToEmployeeAction;
        $employee = $action->execute($checklist);

        expect($employee->department_id)->toBe($department->id);
        expect($employee->position_id)->toBe($position->id);
    });

    it('prevents duplicate employee creation', function () {
        $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
        $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
            'job_application_id' => $application->id,
        ]);
        $checklist = PreboardingChecklist::factory()->create([
            'job_application_id' => $application->id,
            'offer_id' => $offer->id,
            'status' => PreboardingStatus::Completed,
        ]);

        // Create an existing employee with the same email
        Employee::factory()->create(['email' => $application->candidate->email]);

        $action = new ConvertToEmployeeAction;

        expect(fn () => $action->execute($checklist))
            ->toThrow(\Illuminate\Validation\ValidationException::class);
    });
});

describe('Convert to Employee API', function () {
    it('converts completed checklist to employee via API', function () {
        $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
        $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
            'job_application_id' => $application->id,
            'created_by' => $this->user->id,
        ]);
        $checklist = PreboardingChecklist::factory()->create([
            'job_application_id' => $application->id,
            'offer_id' => $offer->id,
            'status' => PreboardingStatus::Completed,
            'completed_at' => now(),
            'created_by' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $controller = new \App\Http\Controllers\Api\PreboardingReviewController(
            app(\App\Services\PreboardingService::class)
        );
        $response = $controller->convertToEmployee($checklist);

        expect($response->getStatusCode())->toBe(302);
        expect(Employee::where('email', $application->candidate->email)->exists())->toBeTrue();
    });

    it('rejects conversion for non-completed checklist', function () {
        $application = JobApplication::factory()->withStatus(ApplicationStatus::Hired)->create();
        $offer = Offer::factory()->withStatus(OfferStatus::Accepted)->create([
            'job_application_id' => $application->id,
        ]);
        $checklist = PreboardingChecklist::factory()->create([
            'job_application_id' => $application->id,
            'offer_id' => $offer->id,
            'status' => PreboardingStatus::InProgress, // Not completed
        ]);

        $this->actingAs($this->user);

        $controller = new \App\Http\Controllers\Api\PreboardingReviewController(
            app(\App\Services\PreboardingService::class)
        );

        expect(fn () => $controller->convertToEmployee($checklist))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('requires can-manage-employees permission', function () {
        Gate::define('can-manage-employees', fn () => false);

        $checklist = PreboardingChecklist::factory()->create([
            'status' => PreboardingStatus::Completed,
        ]);

        $this->actingAs($this->user);

        $controller = new \App\Http\Controllers\Api\PreboardingReviewController(
            app(\App\Services\PreboardingService::class)
        );

        expect(fn () => $controller->convertToEmployee($checklist))
            ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    });
});
