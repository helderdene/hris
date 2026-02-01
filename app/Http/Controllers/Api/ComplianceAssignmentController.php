<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplianceAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\BulkComplianceAssignmentRequest;
use App\Http\Requests\StoreComplianceAssignmentRequest;
use App\Http\Resources\ComplianceAssignmentResource;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceCourse;
use App\Models\Employee;
use App\Services\ComplianceAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ComplianceAssignmentController extends Controller
{
    public function __construct(
        protected ComplianceAssignmentService $assignmentService
    ) {}

    /**
     * Display a listing of compliance assignments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-training');

        $query = ComplianceAssignment::query()
            ->with([
                'complianceCourse.course',
                'employee',
                'assignmentRule',
                'certificate',
            ])
            ->orderByDesc('created_at');

        // Filter by compliance course
        if ($request->filled('compliance_course_id')) {
            $query->where('compliance_course_id', $request->input('compliance_course_id'));
        }

        // Filter by employee
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = ComplianceAssignmentStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->byStatus($status);
            }
        }

        // Filter by department (via employee)
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        // Filter by due date range
        if ($request->filled('due_from')) {
            $query->where('due_date', '>=', $request->input('due_from'));
        }
        if ($request->filled('due_until')) {
            $query->where('due_date', '<=', $request->input('due_until'));
        }

        // Filter overdue
        if ($request->boolean('overdue_only')) {
            $query->pastDue();
        }

        // Filter due soon
        if ($request->filled('due_within_days')) {
            $query->dueSoon((int) $request->input('due_within_days'));
        }

        $assignments = $query->paginate($request->input('per_page', 20));

        return ComplianceAssignmentResource::collection($assignments);
    }

    /**
     * Store a newly created assignment (manual assignment).
     */
    public function store(StoreComplianceAssignmentRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();

        $complianceCourse = ComplianceCourse::findOrFail($validated['compliance_course_id']);
        $employee = Employee::findOrFail($validated['employee_id']);
        $assignedBy = auth()->user()->employee;

        $assignment = $this->assignmentService->assignToEmployee(
            $complianceCourse,
            $employee,
            $assignedBy,
            $validated['days_to_complete'] ?? null
        );

        $assignment->load([
            'complianceCourse.course',
            'employee',
            'progress.complianceModule',
        ]);

        return (new ComplianceAssignmentResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Bulk assign a compliance course to multiple employees.
     */
    public function bulkAssign(BulkComplianceAssignmentRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();

        $complianceCourse = ComplianceCourse::findOrFail($validated['compliance_course_id']);
        $assignedBy = auth()->user()->employee;

        $assignments = $this->assignmentService->bulkAssign(
            $complianceCourse,
            $validated['employee_ids'],
            $assignedBy,
            $validated['days_to_complete'] ?? null
        );

        return response()->json([
            'message' => $assignments->count().' assignments created successfully.',
            'created_count' => $assignments->count(),
            'assignment_ids' => $assignments->pluck('id'),
        ], 201);
    }

    /**
     * Display the specified assignment.
     */
    public function show(string $tenant, ComplianceAssignment $complianceAssignment): ComplianceAssignmentResource
    {
        Gate::authorize('can-view-training');

        $complianceAssignment->load([
            'complianceCourse.course',
            'complianceCourse.modules',
            'employee',
            'assignmentRule',
            'progress.complianceModule',
            'certificate',
        ]);

        return new ComplianceAssignmentResource($complianceAssignment);
    }

    /**
     * Extend the due date for an assignment.
     */
    public function extend(
        Request $request,
        string $tenant,
        ComplianceAssignment $complianceAssignment
    ): ComplianceAssignmentResource {
        Gate::authorize('can-manage-training');

        $request->validate([
            'new_due_date' => ['required', 'date', 'after:today'],
        ]);

        if ($complianceAssignment->status->isTerminal()) {
            abort(422, 'Cannot extend due date for a completed or exempted assignment.');
        }

        $complianceAssignment->update([
            'due_date' => $request->input('new_due_date'),
        ]);

        // If was overdue, change back to in_progress
        if ($complianceAssignment->status === ComplianceAssignmentStatus::Overdue) {
            $status = $complianceAssignment->started_at
                ? ComplianceAssignmentStatus::InProgress
                : ComplianceAssignmentStatus::Pending;
            $complianceAssignment->update(['status' => $status]);
        }

        $complianceAssignment->load([
            'complianceCourse.course',
            'employee',
            'progress.complianceModule',
        ]);

        return new ComplianceAssignmentResource($complianceAssignment);
    }

    /**
     * Exempt an employee from an assignment.
     */
    public function exempt(
        Request $request,
        string $tenant,
        ComplianceAssignment $complianceAssignment
    ): ComplianceAssignmentResource {
        Gate::authorize('can-manage-training');

        $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($complianceAssignment->status === ComplianceAssignmentStatus::Completed) {
            abort(422, 'Cannot exempt a completed assignment.');
        }

        $exemptedBy = auth()->user()->employee;
        $this->assignmentService->exemptEmployee(
            $complianceAssignment,
            $exemptedBy,
            $request->input('reason')
        );

        $complianceAssignment->load([
            'complianceCourse.course',
            'employee',
            'exemptedByEmployee',
        ]);

        return new ComplianceAssignmentResource($complianceAssignment);
    }

    /**
     * Revoke an exemption.
     */
    public function revokeExemption(
        Request $request,
        string $tenant,
        ComplianceAssignment $complianceAssignment
    ): ComplianceAssignmentResource {
        Gate::authorize('can-manage-training');

        if ($complianceAssignment->status !== ComplianceAssignmentStatus::Exempted) {
            abort(422, 'Assignment is not exempted.');
        }

        $this->assignmentService->revokeExemption(
            $complianceAssignment,
            $request->input('days_to_complete')
        );

        $complianceAssignment->load([
            'complianceCourse.course',
            'employee',
            'progress.complianceModule',
        ]);

        return new ComplianceAssignmentResource($complianceAssignment);
    }

    /**
     * Remove the specified assignment.
     */
    public function destroy(string $tenant, ComplianceAssignment $complianceAssignment): JsonResponse
    {
        Gate::authorize('can-manage-training');

        // Only allow deletion of pending assignments
        if ($complianceAssignment->status !== ComplianceAssignmentStatus::Pending) {
            return response()->json([
                'message' => 'Only pending assignments can be deleted.',
            ], 422);
        }

        $complianceAssignment->delete();

        return response()->json([
            'message' => 'Assignment deleted successfully.',
        ]);
    }

    /**
     * Reassign an expired or completed assignment (for recertification).
     */
    public function reassign(
        Request $request,
        string $tenant,
        ComplianceAssignment $complianceAssignment
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        $newAssignment = $this->assignmentService->reassign(
            $complianceAssignment,
            $request->input('days_to_complete')
        );

        $newAssignment->load([
            'complianceCourse.course',
            'employee',
            'progress.complianceModule',
        ]);

        return (new ComplianceAssignmentResource($newAssignment))
            ->response()
            ->setStatusCode(201);
    }
}
