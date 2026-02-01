<?php

namespace App\Http\Controllers\Recruitment;

use App\Enums\EmploymentType;
use App\Enums\JobPostingStatus;
use App\Enums\SalaryDisplayOption;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\Position;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobPostingPageController extends Controller
{
    /**
     * Display the job postings index page.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');
        $departmentId = $request->input('department_id');

        $query = JobPosting::query()
            ->with(['department', 'position', 'createdByEmployee'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $postings = $query->paginate(25)->through(fn (JobPosting $posting) => [
            'id' => $posting->id,
            'slug' => $posting->slug,
            'title' => $posting->title,
            'department' => [
                'id' => $posting->department->id,
                'name' => $posting->department->name,
            ],
            'position' => $posting->position ? [
                'id' => $posting->position->id,
                'name' => $posting->position->title,
            ] : null,
            'created_by_employee' => [
                'id' => $posting->createdByEmployee->id,
                'full_name' => $posting->createdByEmployee->full_name,
            ],
            'employment_type' => $posting->employment_type->value,
            'employment_type_label' => $posting->employment_type->label(),
            'location' => $posting->location,
            'status' => $posting->status->value,
            'status_label' => $posting->status->label(),
            'status_color' => $posting->status->color(),
            'published_at' => $posting->published_at?->format('Y-m-d H:i:s'),
            'created_at' => $posting->created_at->format('Y-m-d H:i:s'),
            'can_be_edited' => $posting->can_be_edited,
            'can_be_published' => $posting->can_be_published,
            'can_be_closed' => $posting->can_be_closed,
        ]);

        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $departments = Department::query()->orderBy('name')->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
        ]);

        $positions = Position::query()->orderBy('title')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->title,
        ]);

        return Inertia::render('Recruitment/JobPostings/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'postings' => $postings,
            'departments' => $departments,
            'positions' => $positions,
            'statuses' => JobPostingStatus::options(),
            'employmentTypes' => array_map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], EmploymentType::cases()),
            'salaryDisplayOptions' => SalaryDisplayOption::options(),
            'filters' => [
                'status' => $status,
                'department_id' => $departmentId,
            ],
        ]);
    }

    /**
     * Display the create job posting page.
     */
    public function create(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $departments = Department::query()->orderBy('name')->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
        ]);

        $positions = Position::query()->orderBy('title')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->title,
        ]);

        $requisition = null;
        if ($request->filled('requisition_id')) {
            $req = JobRequisition::with(['position', 'department'])->find($request->input('requisition_id'));
            if ($req) {
                $requisition = [
                    'id' => $req->id,
                    'reference_number' => $req->reference_number,
                    'position_id' => $req->position_id,
                    'department_id' => $req->department_id,
                    'title' => $req->position?->title,
                    'employment_type' => $req->employment_type->value,
                    'salary_range_min' => $req->salary_range_min ? (float) $req->salary_range_min : null,
                    'salary_range_max' => $req->salary_range_max ? (float) $req->salary_range_max : null,
                ];
            }
        }

        return Inertia::render('Recruitment/JobPostings/Create', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
            ] : null,
            'departments' => $departments,
            'positions' => $positions,
            'employmentTypes' => array_map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], EmploymentType::cases()),
            'salaryDisplayOptions' => SalaryDisplayOption::options(),
            'requisition' => $requisition,
        ]);
    }

    /**
     * Display a specific job posting.
     */
    public function show(string $tenant, JobPosting $jobPosting): Response
    {
        $jobPosting->load(['department', 'position', 'createdByEmployee', 'jobRequisition']);

        return Inertia::render('Recruitment/JobPostings/Show', [
            'posting' => [
                'id' => $jobPosting->id,
                'slug' => $jobPosting->slug,
                'title' => $jobPosting->title,
                'job_requisition' => $jobPosting->jobRequisition ? [
                    'id' => $jobPosting->jobRequisition->id,
                    'reference_number' => $jobPosting->jobRequisition->reference_number,
                ] : null,
                'department' => [
                    'id' => $jobPosting->department->id,
                    'name' => $jobPosting->department->name,
                ],
                'position' => $jobPosting->position ? [
                    'id' => $jobPosting->position->id,
                    'name' => $jobPosting->position->title,
                ] : null,
                'created_by_employee' => [
                    'id' => $jobPosting->createdByEmployee->id,
                    'full_name' => $jobPosting->createdByEmployee->full_name,
                ],
                'description' => $jobPosting->description,
                'requirements' => $jobPosting->requirements,
                'benefits' => $jobPosting->benefits,
                'employment_type' => $jobPosting->employment_type->value,
                'employment_type_label' => $jobPosting->employment_type->label(),
                'location' => $jobPosting->location,
                'salary_display_option' => $jobPosting->salary_display_option?->value,
                'salary_display_option_label' => $jobPosting->salary_display_option?->label(),
                'salary_range_min' => $jobPosting->salary_range_min ? (float) $jobPosting->salary_range_min : null,
                'salary_range_max' => $jobPosting->salary_range_max ? (float) $jobPosting->salary_range_max : null,
                'application_instructions' => $jobPosting->application_instructions,
                'status' => $jobPosting->status->value,
                'status_label' => $jobPosting->status->label(),
                'status_color' => $jobPosting->status->color(),
                'published_at' => $jobPosting->published_at?->format('Y-m-d H:i:s'),
                'closed_at' => $jobPosting->closed_at?->format('Y-m-d H:i:s'),
                'created_at' => $jobPosting->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $jobPosting->can_be_edited,
                'can_be_published' => $jobPosting->can_be_published,
                'can_be_closed' => $jobPosting->can_be_closed,
            ],
        ]);
    }

    /**
     * Display the edit job posting page.
     */
    public function edit(string $tenant, JobPosting $jobPosting): Response
    {
        $jobPosting->load(['department', 'position']);

        $departments = Department::query()->orderBy('name')->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
        ]);

        $positions = Position::query()->orderBy('title')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->title,
        ]);

        return Inertia::render('Recruitment/JobPostings/Edit', [
            'posting' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title,
                'department_id' => $jobPosting->department_id,
                'position_id' => $jobPosting->position_id,
                'description' => $jobPosting->description,
                'requirements' => $jobPosting->requirements,
                'benefits' => $jobPosting->benefits,
                'employment_type' => $jobPosting->employment_type->value,
                'location' => $jobPosting->location,
                'salary_display_option' => $jobPosting->salary_display_option?->value,
                'salary_range_min' => $jobPosting->salary_range_min ? (float) $jobPosting->salary_range_min : null,
                'salary_range_max' => $jobPosting->salary_range_max ? (float) $jobPosting->salary_range_max : null,
                'application_instructions' => $jobPosting->application_instructions,
                'status' => $jobPosting->status->value,
                'can_be_edited' => $jobPosting->can_be_edited,
            ],
            'departments' => $departments,
            'positions' => $positions,
            'employmentTypes' => array_map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], EmploymentType::cases()),
            'salaryDisplayOptions' => SalaryDisplayOption::options(),
        ]);
    }
}
