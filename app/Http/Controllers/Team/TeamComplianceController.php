<?php

namespace App\Http\Controllers\Team;

use App\Enums\ComplianceAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComplianceAssignmentResource;
use App\Models\ComplianceAssignment;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TeamComplianceController extends Controller
{
    /**
     * Display the manager's team compliance dashboard.
     */
    public function index(Request $request): Response
    {
        $manager = $this->getAuthenticatedEmployee();

        if (! $manager) {
            return Inertia::render('Team/Compliance/Index', [
                'assignments' => [],
                'stats' => $this->getEmptyStats(),
                'teamMembers' => [],
            ]);
        }

        // Get direct reports
        $directReportIds = Employee::where('supervisor_id', $manager->id)
            ->pluck('id');

        $query = ComplianceAssignment::query()
            ->whereIn('employee_id', $directReportIds)
            ->with(['employee', 'complianceCourse.course'])
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

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $assignments = $query->get();

        $stats = $this->getTeamStats($directReportIds);

        $teamMembers = Employee::whereIn('id', $directReportIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'employee_number', 'first_name', 'last_name'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'employee_number' => $e->employee_number,
            ]);

        return Inertia::render('Team/Compliance/Index', [
            'assignments' => ComplianceAssignmentResource::collection($assignments),
            'stats' => $stats,
            'teamMembers' => $teamMembers,
            'filters' => [
                'status' => $request->input('status'),
                'employee_id' => $request->input('employee_id'),
            ],
            'statusOptions' => $this->getStatusOptions(),
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
     * Get team compliance statistics.
     *
     * @param  \Illuminate\Support\Collection<int, int>  $employeeIds
     * @return array<string, mixed>
     */
    private function getTeamStats(\Illuminate\Support\Collection $employeeIds): array
    {
        if ($employeeIds->isEmpty()) {
            return $this->getEmptyStats();
        }

        $assignments = ComplianceAssignment::whereIn('employee_id', $employeeIds)->get();

        $total = $assignments->count();
        $completed = $assignments->where('status', ComplianceAssignmentStatus::Completed)->count();
        $overdue = $assignments->where('status', ComplianceAssignmentStatus::Overdue)->count();
        $inProgress = $assignments->where('status', ComplianceAssignmentStatus::InProgress)->count();
        $pending = $assignments->where('status', ComplianceAssignmentStatus::Pending)->count();

        return [
            'team_size' => $employeeIds->count(),
            'total_assignments' => $total,
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
            'team_size' => 0,
            'total_assignments' => 0,
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
