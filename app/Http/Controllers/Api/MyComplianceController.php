<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplianceAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComplianceAssignmentResource;
use App\Http\Resources\ComplianceCertificateResource;
use App\Http\Resources\ComplianceModuleResource;
use App\Http\Resources\ComplianceProgressResource;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceModule;
use App\Services\ComplianceProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class MyComplianceController extends Controller
{
    public function __construct(
        protected ComplianceProgressService $progressService
    ) {}

    /**
     * Get the authenticated employee's compliance assignments.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            abort(403, 'No employee record found.');
        }

        $query = $employee->complianceAssignments()
            ->with([
                'complianceCourse.course',
                'complianceCourse.modules',
                'progress.complianceModule',
                'certificate',
            ])
            ->orderByRaw("CASE
                WHEN status = 'overdue' THEN 1
                WHEN status = 'in_progress' THEN 2
                WHEN status = 'pending' THEN 3
                ELSE 4
            END")
            ->orderBy('due_date');

        // Filter by status
        if ($request->filled('status')) {
            $status = ComplianceAssignmentStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->byStatus($status);
            }
        }

        // Active assignments only
        if ($request->boolean('active_only')) {
            $query->active();
        }

        // Completed assignments only
        if ($request->boolean('completed_only')) {
            $query->completed();
        }

        $assignments = $query->get();

        return ComplianceAssignmentResource::collection($assignments);
    }

    /**
     * Get a specific assignment details.
     */
    public function show(ComplianceAssignment $complianceAssignment): ComplianceAssignmentResource
    {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        $complianceAssignment->load([
            'complianceCourse.course',
            'complianceCourse.modules.assessments',
            'progress.complianceModule',
            'certificate',
        ]);

        return new ComplianceAssignmentResource($complianceAssignment);
    }

    /**
     * Start a module.
     */
    public function startModule(
        ComplianceAssignment $complianceAssignment,
        ComplianceModule $complianceModule
    ): ComplianceProgressResource {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        // Ensure module belongs to the assignment's course
        if ($complianceModule->compliance_course_id !== $complianceAssignment->compliance_course_id) {
            abort(404, 'Module not found in this course.');
        }

        // Cannot start if assignment is completed or exempted
        if ($complianceAssignment->status->isTerminal()) {
            abort(422, 'This assignment is already completed or exempted.');
        }

        $progress = $this->progressService->startModule($complianceAssignment, $complianceModule);

        return new ComplianceProgressResource($progress);
    }

    /**
     * Update module progress.
     */
    public function updateProgress(
        Request $request,
        ComplianceAssignment $complianceAssignment,
        ComplianceModule $complianceModule
    ): ComplianceProgressResource {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        $request->validate([
            'percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'position_data' => ['nullable', 'array'],
            'time_spent_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $progress = $this->progressService->getOrCreateProgress($complianceAssignment, $complianceModule);

        $progress = $this->progressService->updateProgress(
            $progress,
            $request->input('percentage'),
            $request->input('position_data'),
            $request->input('time_spent_minutes', 0)
        );

        return new ComplianceProgressResource($progress);
    }

    /**
     * Complete a non-assessment module.
     */
    public function completeModule(
        ComplianceAssignment $complianceAssignment,
        ComplianceModule $complianceModule
    ): ComplianceProgressResource {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        // Cannot complete assessment modules this way
        if ($complianceModule->isAssessment()) {
            abort(422, 'Assessment modules must be completed by submitting the assessment.');
        }

        $progress = $this->progressService->getOrCreateProgress($complianceAssignment, $complianceModule);
        $progress = $this->progressService->completeModule($progress);

        return new ComplianceProgressResource($progress);
    }

    /**
     * Acknowledge training (if required).
     */
    public function acknowledge(
        ComplianceAssignment $complianceAssignment
    ): ComplianceAssignmentResource {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        if (! $complianceAssignment->complianceCourse->requires_acknowledgment) {
            abort(422, 'This course does not require acknowledgment.');
        }

        if ($complianceAssignment->acknowledgment_completed) {
            abort(422, 'You have already acknowledged this training.');
        }

        $complianceAssignment->update([
            'acknowledgment_completed' => true,
            'acknowledged_at' => now(),
        ]);

        return new ComplianceAssignmentResource($complianceAssignment);
    }

    /**
     * Get the employee's compliance certificates.
     */
    public function certificates(): AnonymousResourceCollection
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            abort(403, 'No employee record found.');
        }

        $certificates = $employee->complianceAssignments()
            ->completed()
            ->has('certificate')
            ->with(['certificate', 'complianceCourse.course'])
            ->get()
            ->pluck('certificate')
            ->sortByDesc('issued_date');

        return ComplianceCertificateResource::collection($certificates);
    }

    /**
     * Download a certificate.
     */
    public function downloadCertificate(
        ComplianceAssignment $complianceAssignment
    ): \Symfony\Component\HttpFoundation\StreamedResponse {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this certificate.');
        }

        $certificate = $complianceAssignment->certificate;

        if (! $certificate) {
            abort(404, 'No certificate found for this assignment.');
        }

        if (! $certificate->file_path) {
            abort(404, 'Certificate file not available.');
        }

        return \Storage::disk('private')->download(
            $certificate->file_path,
            $certificate->file_name ?? 'certificate.pdf'
        );
    }

    /**
     * Get compliance summary stats for the current employee.
     */
    public function summary(): JsonResponse
    {
        $employee = auth()->user()->employee;

        if (! $employee) {
            abort(403, 'No employee record found.');
        }

        $assignments = $employee->complianceAssignments;

        return response()->json([
            'total' => $assignments->count(),
            'pending' => $assignments->where('status', ComplianceAssignmentStatus::Pending)->count(),
            'in_progress' => $assignments->where('status', ComplianceAssignmentStatus::InProgress)->count(),
            'completed' => $assignments->where('status', ComplianceAssignmentStatus::Completed)->count(),
            'overdue' => $assignments->where('status', ComplianceAssignmentStatus::Overdue)->count(),
            'certificates' => $employee->complianceAssignments()
                ->completed()
                ->has('certificate')
                ->count(),
        ]);
    }

    /**
     * Get the next module to complete.
     */
    public function nextModule(
        ComplianceAssignment $complianceAssignment
    ): JsonResponse {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        $nextModule = $this->progressService->getNextModule($complianceAssignment);

        if (! $nextModule) {
            return response()->json([
                'message' => 'All modules completed.',
                'module' => null,
            ]);
        }

        return response()->json([
            'message' => 'Next module found.',
            'module' => new ComplianceModuleResource($nextModule),
        ]);
    }
}
