<?php

use App\Enums\SalaryDisplayOption;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $this->withoutVite();

    $this->tenant = Tenant::factory()->create();
    app()->instance('tenant', $this->tenant);
    $this->department = Department::factory()->create();
    $this->employee = Employee::factory()->create();
    $this->baseUrl = "http://{$this->tenant->slug}.kasamahr.test";
});

it('shows the public careers page without authentication', function () {
    $response = $this->get("{$this->baseUrl}/careers");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page->component('Careers/Index'));
});

it('only shows published postings on careers page', function () {
    JobPosting::factory()->draft()->create([
        'title' => 'Draft Job',
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    JobPosting::factory()->published()->create([
        'title' => 'Published Job',
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    JobPosting::factory()->closed()->create([
        'title' => 'Closed Job',
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    $response = $this->get("{$this->baseUrl}/careers");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Careers/Index')
        ->has('postings.data', 1)
        ->where('postings.data.0.title', 'Published Job')
    );
});

it('can filter careers by department', function () {
    $dept1 = Department::factory()->create(['name' => 'Engineering']);
    $dept2 = Department::factory()->create(['name' => 'Marketing']);

    JobPosting::factory()->published()->create([
        'title' => 'Engineer',
        'department_id' => $dept1->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    JobPosting::factory()->published()->create([
        'title' => 'Marketer',
        'department_id' => $dept2->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    $response = $this->get("{$this->baseUrl}/careers?department_id={$dept1->id}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('postings.data', 1)
        ->where('postings.data.0.title', 'Engineer')
    );
});

it('can search careers by title', function () {
    JobPosting::factory()->published()->create([
        'title' => 'Senior Software Engineer',
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    JobPosting::factory()->published()->create([
        'title' => 'Marketing Manager',
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    $response = $this->get("{$this->baseUrl}/careers?search=Software");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('postings.data', 1)
        ->where('postings.data.0.title', 'Senior Software Engineer')
    );
});

it('shows a published posting detail page by slug', function () {
    $posting = JobPosting::factory()->published()->create([
        'title' => 'Full Stack Developer',
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
        'description' => 'Great opportunity!',
    ]);

    $response = $this->get("{$this->baseUrl}/careers/{$posting->slug}");

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Careers/Show')
        ->where('posting.title', 'Full Stack Developer')
        ->where('posting.description', 'Great opportunity!')
    );
});

it('returns 404 for non-published posting slug', function () {
    $posting = JobPosting::factory()->draft()->create([
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    $response = $this->get("{$this->baseUrl}/careers/{$posting->slug}");

    $response->assertNotFound();
});

it('returns 404 for non-existent slug', function () {
    $response = $this->get("{$this->baseUrl}/careers/nonexistent-slug");

    $response->assertNotFound();
});

it('displays correct salary based on display option', function () {
    $exactRange = JobPosting::factory()->published()->create([
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
        'salary_display_option' => SalaryDisplayOption::ExactRange,
        'salary_range_min' => 50000,
        'salary_range_max' => 80000,
    ]);

    $hidden = JobPosting::factory()->published()->create([
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
        'salary_display_option' => SalaryDisplayOption::Hidden,
        'salary_range_min' => 50000,
        'salary_range_max' => 80000,
    ]);

    $negotiable = JobPosting::factory()->published()->create([
        'department_id' => $this->department->id,
        'created_by_employee_id' => $this->employee->id,
        'salary_display_option' => SalaryDisplayOption::Negotiable,
    ]);

    $response = $this->get("{$this->baseUrl}/careers");
    $response->assertSuccessful();

    $data = $response->original->getData()['page']['props']['postings']['data'];

    $exactData = collect($data)->firstWhere('id', $exactRange->id);
    $hiddenData = collect($data)->firstWhere('id', $hidden->id);
    $negotiableData = collect($data)->firstWhere('id', $negotiable->id);

    expect($exactData['salary_display'])->toContain('50,000');
    expect($hiddenData['salary_display'])->toBeNull();
    expect($negotiableData['salary_display'])->toBe('Negotiable');
});

it('only shows departments with published postings in filter', function () {
    $deptWithJobs = Department::factory()->create(['name' => 'Active Dept']);
    $deptWithoutJobs = Department::factory()->create(['name' => 'Empty Dept']);

    JobPosting::factory()->published()->create([
        'department_id' => $deptWithJobs->id,
        'created_by_employee_id' => $this->employee->id,
    ]);

    $response = $this->get("{$this->baseUrl}/careers");
    $response->assertSuccessful();

    $departments = $response->original->getData()['page']['props']['departments'];
    $deptIds = collect($departments)->pluck('id')->all();

    expect($deptIds)->toContain($deptWithJobs->id);
    expect($deptIds)->not->toContain($deptWithoutJobs->id);
});
