<?php

namespace App\Http\Controllers\Recruitment;

use App\Enums\EmploymentType;
use App\Enums\JobRequisitionStatus;
use App\Enums\JobRequisitionUrgency;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Models\Position;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobRequisitionPageController extends Controller
{
    /**
     * Display the job requisitions index page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $status = $request->input('status');
        $urgency = $request->input('urgency');
        $departmentId = $request->input('department_id');

        $query = JobRequisition::query()
            ->with(['position', 'department', 'requestedByEmployee'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        if ($urgency) {
            $query->where('urgency', $urgency);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        $requisitions = $query->paginate(25)->through(fn ($req) => [
            'id' => $req->id,
            'reference_number' => $req->reference_number,
            'position' => [
                'id' => $req->position->id,
                'name' => $req->position->title,
            ],
            'department' => [
                'id' => $req->department->id,
                'name' => $req->department->name,
            ],
            'requested_by' => [
                'id' => $req->requestedByEmployee->id,
                'full_name' => $req->requestedByEmployee->full_name,
            ],
            'headcount' => $req->headcount,
            'employment_type' => $req->employment_type->value,
            'employment_type_label' => $req->employment_type->label(),
            'urgency' => $req->urgency->value,
            'urgency_label' => $req->urgency->label(),
            'urgency_color' => $req->urgency->color(),
            'status' => $req->status->value,
            'status_label' => $req->status->label(),
            'status_color' => $req->status->color(),
            'submitted_at' => $req->submitted_at?->format('Y-m-d H:i:s'),
            'created_at' => $req->created_at->format('Y-m-d H:i:s'),
            'can_be_edited' => $req->can_be_edited,
            'can_be_cancelled' => $req->can_be_cancelled,
        ]);

        $departments = Department::query()->orderBy('name')->get()->map(fn ($d) => [
            'id' => $d->id,
            'name' => $d->name,
        ]);

        $positions = Position::query()->orderBy('title')->get()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->title,
        ]);

        return Inertia::render('Recruitment/Requisitions/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ] : null,
            'requisitions' => $requisitions,
            'departments' => $departments,
            'positions' => $positions,
            'statuses' => JobRequisitionStatus::options(),
            'urgencies' => JobRequisitionUrgency::options(),
            'employmentTypes' => array_map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ], EmploymentType::cases()),
            'filters' => [
                'status' => $status,
                'urgency' => $urgency,
                'department_id' => $departmentId,
            ],
        ]);
    }

    /**
     * Display a specific job requisition.
     */
    public function show(string $tenant, JobRequisition $jobRequisition): Response
    {
        $jobRequisition->load([
            'position',
            'department',
            'requestedByEmployee.department',
            'requestedByEmployee.position',
            'approvals.approverEmployee',
        ]);

        return Inertia::render('Recruitment/Requisitions/Show', [
            'requisition' => [
                'id' => $jobRequisition->id,
                'reference_number' => $jobRequisition->reference_number,
                'position' => [
                    'id' => $jobRequisition->position->id,
                    'name' => $jobRequisition->position->title,
                ],
                'department' => [
                    'id' => $jobRequisition->department->id,
                    'name' => $jobRequisition->department->name,
                ],
                'requested_by' => [
                    'id' => $jobRequisition->requestedByEmployee->id,
                    'full_name' => $jobRequisition->requestedByEmployee->full_name,
                    'employee_number' => $jobRequisition->requestedByEmployee->employee_number,
                    'department' => $jobRequisition->requestedByEmployee->department?->name,
                    'position' => $jobRequisition->requestedByEmployee->position?->title,
                ],
                'headcount' => $jobRequisition->headcount,
                'employment_type' => $jobRequisition->employment_type->value,
                'employment_type_label' => $jobRequisition->employment_type->label(),
                'salary_range_min' => $jobRequisition->salary_range_min ? (float) $jobRequisition->salary_range_min : null,
                'salary_range_max' => $jobRequisition->salary_range_max ? (float) $jobRequisition->salary_range_max : null,
                'justification' => $jobRequisition->justification,
                'urgency' => $jobRequisition->urgency->value,
                'urgency_label' => $jobRequisition->urgency->label(),
                'urgency_color' => $jobRequisition->urgency->color(),
                'preferred_start_date' => $jobRequisition->preferred_start_date?->format('Y-m-d'),
                'requirements' => $jobRequisition->requirements,
                'remarks' => $jobRequisition->remarks,
                'status' => $jobRequisition->status->value,
                'status_label' => $jobRequisition->status->label(),
                'status_color' => $jobRequisition->status->color(),
                'current_approval_level' => $jobRequisition->current_approval_level,
                'total_approval_levels' => $jobRequisition->total_approval_levels,
                'approvals' => $jobRequisition->approvals->map(fn ($approval) => [
                    'id' => $approval->id,
                    'approval_level' => $approval->approval_level,
                    'approver_type' => $approval->approver_type,
                    'approver_name' => $approval->approver_name,
                    'approver_position' => $approval->approver_position,
                    'decision' => $approval->decision->value,
                    'decision_label' => $approval->decision->label(),
                    'decision_color' => $approval->decision->color(),
                    'remarks' => $approval->remarks,
                    'decided_at' => $approval->decided_at?->format('Y-m-d H:i:s'),
                ]),
                'cancellation_reason' => $jobRequisition->cancellation_reason,
                'submitted_at' => $jobRequisition->submitted_at?->format('Y-m-d H:i:s'),
                'approved_at' => $jobRequisition->approved_at?->format('Y-m-d H:i:s'),
                'rejected_at' => $jobRequisition->rejected_at?->format('Y-m-d H:i:s'),
                'cancelled_at' => $jobRequisition->cancelled_at?->format('Y-m-d H:i:s'),
                'created_at' => $jobRequisition->created_at->format('Y-m-d H:i:s'),
                'can_be_edited' => $jobRequisition->can_be_edited,
                'can_be_cancelled' => $jobRequisition->can_be_cancelled,
            ],
        ]);
    }
}
