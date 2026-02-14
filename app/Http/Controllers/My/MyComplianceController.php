<?php

namespace App\Http\Controllers\My;

use App\Enums\ComplianceAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComplianceAssignmentResource;
use App\Http\Resources\ComplianceCertificateResource;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceCertificate;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class MyComplianceController extends Controller
{
    /**
     * Display the employee's compliance training dashboard.
     */
    public function index(Request $request): Response
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return Inertia::render('My/Compliance/Index', [
                'assignments' => [],
                'stats' => $this->getEmptyStats(),
            ]);
        }

        $query = $employee->complianceAssignments()
            ->with(['complianceCourse.course', 'progress.complianceModule'])
            ->orderByRaw("CASE
                WHEN status = 'overdue' THEN 1
                WHEN status = 'in_progress' THEN 2
                WHEN status = 'pending' THEN 3
                WHEN status = 'completed' THEN 4
                ELSE 5
            END")
            ->orderBy('due_date');

        if ($request->filled('status')) {
            $status = ComplianceAssignmentStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        $assignments = $query->get();

        $stats = $this->getComplianceStats($employee);

        return Inertia::render('My/Compliance/Index', [
            'assignments' => ComplianceAssignmentResource::collection($assignments),
            'stats' => $stats,
            'filters' => [
                'status' => $request->input('status'),
            ],
            'statusOptions' => $this->getStatusOptions(),
        ]);
    }

    /**
     * Display a specific compliance assignment with content player.
     */
    public function show(ComplianceAssignment $assignment): Response
    {
        $employee = $this->getAuthenticatedEmployee();

        // Verify the assignment belongs to this employee
        if (! $employee || $assignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this training.');
        }

        $assignment->load([
            'complianceCourse.course',
            'complianceCourse.modules' => fn ($q) => $q->orderBy('sort_order'),
            'progress.complianceModule',
        ]);

        return Inertia::render('My/Compliance/Show', [
            'assignment' => new ComplianceAssignmentResource($assignment),
        ]);
    }

    /**
     * Display the employee's compliance certificates.
     */
    public function certificates(Request $request): Response
    {
        $employee = $this->getAuthenticatedEmployee();

        if (! $employee) {
            return Inertia::render('My/Compliance/Certificates', [
                'certificates' => [],
            ]);
        }

        $certificates = ComplianceCertificate::query()
            ->whereHas('complianceAssignment', fn ($q) => $q->where('employee_id', $employee->id))
            ->with(['complianceAssignment.complianceCourse.course'])
            ->where('is_revoked', false)
            ->orderBy('issued_date', 'desc')
            ->get();

        return Inertia::render('My/Compliance/Certificates', [
            'certificates' => ComplianceCertificateResource::collection($certificates),
        ]);
    }

    /**
     * Get the authenticated user's employee record.
     */
    private function getAuthenticatedEmployee(): ?Employee
    {
        $user = Auth::user();

        return Employee::where('user_id', $user?->id)->first();
    }

    /**
     * Get compliance statistics for an employee.
     *
     * @return array<string, mixed>
     */
    private function getComplianceStats(Employee $employee): array
    {
        $assignments = $employee->complianceAssignments;

        $total = $assignments->count();
        $completed = $assignments->where('status', ComplianceAssignmentStatus::Completed)->count();
        $overdue = $assignments->where('status', ComplianceAssignmentStatus::Overdue)->count();
        $inProgress = $assignments->where('status', ComplianceAssignmentStatus::InProgress)->count();
        $pending = $assignments->where('status', ComplianceAssignmentStatus::Pending)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'overdue' => $overdue,
            'in_progress' => $inProgress,
            'pending' => $pending,
            'compliance_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 100,
        ];
    }

    /**
     * Get empty statistics structure.
     *
     * @return array<string, mixed>
     */
    private function getEmptyStats(): array
    {
        return [
            'total' => 0,
            'completed' => 0,
            'overdue' => 0,
            'in_progress' => 0,
            'pending' => 0,
            'compliance_rate' => 100,
        ];
    }

    /**
     * Get status options for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getStatusOptions(): array
    {
        return array_map(
            fn (ComplianceAssignmentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            ComplianceAssignmentStatus::cases()
        );
    }
}
