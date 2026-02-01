<?php

use App\Enums\JobPostingStatus;
use App\Enums\SalaryDisplayOption;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\Position;
use App\Models\Tenant;
use App\Services\JobPostingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function bindTenantContextForJobPosting(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('JobPosting Model', function () {
    it('auto-generates unique slug on creation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $department = Department::factory()->create();
        $employee = Employee::factory()->create();

        $posting1 = JobPosting::factory()->create([
            'title' => 'Software Engineer',
            'department_id' => $department->id,
            'created_by_employee_id' => $employee->id,
        ]);

        $posting2 = JobPosting::factory()->create([
            'title' => 'Software Engineer',
            'department_id' => $department->id,
            'created_by_employee_id' => $employee->id,
        ]);

        expect($posting1->slug)->toBe('software-engineer');
        expect($posting2->slug)->toStartWith('software-engineer-');
        expect($posting1->slug)->not->toBe($posting2->slug);
    });

    it('defaults to draft status on creation', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = JobPosting::factory()->create([
            'department_id' => Department::factory()->create()->id,
            'created_by_employee_id' => Employee::factory()->create()->id,
        ]);

        expect($posting->status)->toBe(JobPostingStatus::Draft);
    });

    it('has correct status transition rules', function () {
        expect(JobPostingStatus::Draft->canBeEdited())->toBeTrue();
        expect(JobPostingStatus::Published->canBeEdited())->toBeTrue();
        expect(JobPostingStatus::Closed->canBeEdited())->toBeFalse();
        expect(JobPostingStatus::Archived->canBeEdited())->toBeFalse();

        expect(JobPostingStatus::Draft->canBePublished())->toBeTrue();
        expect(JobPostingStatus::Published->canBePublished())->toBeFalse();
        expect(JobPostingStatus::Closed->canBePublished())->toBeTrue();

        expect(JobPostingStatus::Draft->isPubliclyVisible())->toBeFalse();
        expect(JobPostingStatus::Published->isPubliclyVisible())->toBeTrue();
        expect(JobPostingStatus::Closed->isPubliclyVisible())->toBeFalse();
    });

    it('has allowed transitions', function () {
        expect(JobPostingStatus::Draft->allowedTransitions())->toBe([JobPostingStatus::Published]);
        expect(JobPostingStatus::Published->allowedTransitions())->toBe([JobPostingStatus::Closed]);
        expect(JobPostingStatus::Closed->allowedTransitions())->toBe([JobPostingStatus::Archived, JobPostingStatus::Published]);
        expect(JobPostingStatus::Archived->allowedTransitions())->toBe([]);
    });

    it('scopes published postings', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $department = Department::factory()->create();
        $employee = Employee::factory()->create();

        JobPosting::factory()->draft()->create([
            'department_id' => $department->id,
            'created_by_employee_id' => $employee->id,
        ]);
        JobPosting::factory()->published()->create([
            'department_id' => $department->id,
            'created_by_employee_id' => $employee->id,
        ]);
        JobPosting::factory()->closed()->create([
            'department_id' => $department->id,
            'created_by_employee_id' => $employee->id,
        ]);

        expect(JobPosting::published()->count())->toBe(1);
        expect(JobPosting::publiclyVisible()->count())->toBe(1);
    });

    it('scopes by department', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();
        $employee = Employee::factory()->create();

        JobPosting::factory()->create([
            'department_id' => $dept1->id,
            'created_by_employee_id' => $employee->id,
        ]);
        JobPosting::factory()->create([
            'department_id' => $dept2->id,
            'created_by_employee_id' => $employee->id,
        ]);

        expect(JobPosting::forDepartment($dept1)->count())->toBe(1);
    });

    it('exposes computed attributes', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $department = Department::factory()->create();
        $employee = Employee::factory()->create();

        $draft = JobPosting::factory()->draft()->create([
            'department_id' => $department->id,
            'created_by_employee_id' => $employee->id,
        ]);

        expect($draft->can_be_edited)->toBeTrue();
        expect($draft->can_be_published)->toBeTrue();
        expect($draft->can_be_closed)->toBeFalse();
        expect($draft->is_publicly_visible)->toBeFalse();

        $published = JobPosting::factory()->published()->create([
            'department_id' => $department->id,
            'created_by_employee_id' => $employee->id,
        ]);

        expect($published->can_be_edited)->toBeTrue();
        expect($published->can_be_published)->toBeFalse();
        expect($published->can_be_closed)->toBeTrue();
        expect($published->is_publicly_visible)->toBeTrue();
    });

    it('links to job requisition', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $department = Department::factory()->create();
        $employee = Employee::factory()->create();

        $requisition = JobRequisition::factory()->create([
            'department_id' => $department->id,
        ]);

        $posting = JobPosting::factory()->create([
            'job_requisition_id' => $requisition->id,
            'department_id' => $department->id,
            'created_by_employee_id' => $employee->id,
        ]);

        expect($posting->jobRequisition->id)->toBe($requisition->id);
        expect($requisition->jobPostings)->toHaveCount(1);
        expect($requisition->jobPostings->first()->id)->toBe($posting->id);
    });
});

describe('JobPostingService', function () {
    it('publishes a draft posting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = JobPosting::factory()->draft()->create([
            'department_id' => Department::factory()->create()->id,
            'created_by_employee_id' => Employee::factory()->create()->id,
        ]);

        $service = app(JobPostingService::class);
        $result = $service->publish($posting);

        expect($result->status)->toBe(JobPostingStatus::Published);
        expect($result->published_at)->not->toBeNull();
    });

    it('rejects publishing an already published posting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = JobPosting::factory()->published()->create([
            'department_id' => Department::factory()->create()->id,
            'created_by_employee_id' => Employee::factory()->create()->id,
        ]);

        $service = app(JobPostingService::class);
        $service->publish($posting);
    })->throws(\Illuminate\Validation\ValidationException::class);

    it('closes a published posting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = JobPosting::factory()->published()->create([
            'department_id' => Department::factory()->create()->id,
            'created_by_employee_id' => Employee::factory()->create()->id,
        ]);

        $service = app(JobPostingService::class);
        $result = $service->close($posting);

        expect($result->status)->toBe(JobPostingStatus::Closed);
        expect($result->closed_at)->not->toBeNull();
    });

    it('rejects closing a draft posting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = JobPosting::factory()->draft()->create([
            'department_id' => Department::factory()->create()->id,
            'created_by_employee_id' => Employee::factory()->create()->id,
        ]);

        $service = app(JobPostingService::class);
        $service->close($posting);
    })->throws(\Illuminate\Validation\ValidationException::class);

    it('archives a closed posting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = JobPosting::factory()->closed()->create([
            'department_id' => Department::factory()->create()->id,
            'created_by_employee_id' => Employee::factory()->create()->id,
        ]);

        $service = app(JobPostingService::class);
        $result = $service->archive($posting);

        expect($result->status)->toBe(JobPostingStatus::Archived);
    });

    it('rejects archiving a draft posting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = JobPosting::factory()->draft()->create([
            'department_id' => Department::factory()->create()->id,
            'created_by_employee_id' => Employee::factory()->create()->id,
        ]);

        $service = app(JobPostingService::class);
        $service->archive($posting);
    })->throws(\Illuminate\Validation\ValidationException::class);

    it('can republish a closed posting', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = JobPosting::factory()->closed()->create([
            'department_id' => Department::factory()->create()->id,
            'created_by_employee_id' => Employee::factory()->create()->id,
        ]);

        $service = app(JobPostingService::class);
        $result = $service->publish($posting);

        expect($result->status)->toBe(JobPostingStatus::Published);
        expect($result->closed_at)->toBeNull();
    });

    it('creates a posting from a requisition', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $department = Department::factory()->create();
        $position = Position::factory()->create();
        $employee = Employee::factory()->create();

        $requisition = JobRequisition::factory()->approved()->create([
            'department_id' => $department->id,
            'position_id' => $position->id,
            'salary_range_min' => 50000,
            'salary_range_max' => 80000,
        ]);

        $service = app(JobPostingService::class);
        $posting = $service->createFromRequisition($requisition, $employee->id, [
            'description' => 'Job description here',
            'location' => 'Remote',
        ]);

        expect($posting->job_requisition_id)->toBe($requisition->id);
        expect($posting->department_id)->toBe($department->id);
        expect($posting->position_id)->toBe($position->id);
        expect($posting->status)->toBe(JobPostingStatus::Draft);
        expect((float) $posting->salary_range_min)->toBe(50000.00);
    });
});

describe('JobPosting Validation', function () {
    it('validates required fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForJobPosting($tenant);

        $posting = new JobPosting;
        $request = new \App\Http\Requests\StoreJobPostingRequest;

        $rules = $request->rules();

        expect($rules)->toHaveKeys(['title', 'department_id', 'description', 'employment_type', 'location', 'created_by_employee_id']);
        expect($rules['title'])->toContain('required');
        expect($rules['department_id'])->toContain('required');
        expect($rules['description'])->toContain('required');
    });

    it('validates salary range max gte min', function () {
        $request = new \App\Http\Requests\StoreJobPostingRequest;
        $rules = $request->rules();

        expect($rules['salary_range_max'])->toContain('gte:salary_range_min');
    });
});

describe('SalaryDisplayOption Enum', function () {
    it('has correct values', function () {
        expect(SalaryDisplayOption::values())->toBe([
            'exact_range',
            'range_only',
            'hidden',
            'negotiable',
        ]);
    });

    it('has labels', function () {
        expect(SalaryDisplayOption::ExactRange->label())->toBe('Show Exact Range');
        expect(SalaryDisplayOption::Hidden->label())->toBe('Hidden');
        expect(SalaryDisplayOption::Negotiable->label())->toBe('Negotiable');
    });

    it('returns options array', function () {
        $options = SalaryDisplayOption::options();
        expect($options)->toHaveCount(4);
        expect($options[0])->toHaveKeys(['value', 'label']);
    });
});
