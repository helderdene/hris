<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplianceAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ComplianceAssignmentResource;
use App\Models\ComplianceAssignment;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamComplianceController extends Controller
{
    /**
     * Get compliance assignments for the manager's team.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $manager = auth()->user()->employee;

        if (! $manager) {
            abort(403, 'No employee record found.');
        }

        // Get team member IDs
        $teamMemberIds = $this->getTeamMemberIds($manager);

        if (empty($teamMemberIds)) {
            return ComplianceAssignmentResource::collection(collect());
        }

        $query = ComplianceAssignment::query()
            ->whereIn('employee_id', $teamMemberIds)
            ->with([
                'complianceCourse.course',
                'employee',
                'progress.complianceModule',
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

        // Filter by employee
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // Active assignments only
        if ($request->boolean('active_only')) {
            $query->active();
        }

        // Overdue only
        if ($request->boolean('overdue_only')) {
            $query->overdue();
        }

        $assignments = $query->paginate($request->input('per_page', 20));

        return ComplianceAssignmentResource::collection($assignments);
    }

    /**
     * Get team compliance summary.
     */
    public function summary(): JsonResponse
    {
        $manager = auth()->user()->employee;

        if (! $manager) {
            abort(403, 'No employee record found.');
        }

        $teamMemberIds = $this->getTeamMemberIds($manager);

        if (empty($teamMemberIds)) {
            return response()->json([
                'team_size' => 0,
                'total_assignments' => 0,
                'pending' => 0,
                'in_progress' => 0,
                'completed' => 0,
                'overdue' => 0,
                'compliance_rate' => 100,
            ]);
        }

        $assignments = ComplianceAssignment::whereIn('employee_id', $teamMemberIds);

        $total = $assignments->count();
        $completed = $assignments->clone()->completed()->count();

        return response()->json([
            'team_size' => count($teamMemberIds),
            'total_assignments' => $total,
            'pending' => $assignments->clone()->pending()->count(),
            'in_progress' => $assignments->clone()->inProgress()->count(),
            'completed' => $completed,
            'overdue' => $assignments->clone()->overdue()->count(),
            'compliance_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 100,
        ]);
    }

    /**
     * Get compliance status by team member.
     */
    public function byEmployee(): JsonResponse
    {
        $manager = auth()->user()->employee;

        if (! $manager) {
            abort(403, 'No employee record found.');
        }

        $teamMembers = $manager->subordinates()
            ->active()
            ->with(['position', 'complianceAssignments'])
            ->get();

        $data = $teamMembers->map(function ($employee) {
            $assignments = $employee->complianceAssignments;
            $total = $assignments->count();
            $completed = $assignments->where('status', ComplianceAssignmentStatus::Completed)->count();
            $overdue = $assignments->where('status', ComplianceAssignmentStatus::Overdue)->count();

            return [
                'id' => $employee->id,
                'employee_number' => $employee->employee_number,
                'full_name' => $employee->full_name,
                'position' => $employee->position?->title,
                'total_assignments' => $total,
                'completed' => $completed,
                'in_progress' => $assignments->where('status', ComplianceAssignmentStatus::InProgress)->count(),
                'pending' => $assignments->where('status', ComplianceAssignmentStatus::Pending)->count(),
                'overdue' => $overdue,
                'compliance_rate' => $total > 0 ? round(($completed / $total) * 100, 2) : 100,
            ];
        })->sortByDesc('overdue')->values();

        return response()->json($data);
    }

    /**
     * Get overdue assignments for the team.
     */
    public function overdue(): AnonymousResourceCollection
    {
        $manager = auth()->user()->employee;

        if (! $manager) {
            abort(403, 'No employee record found.');
        }

        $teamMemberIds = $this->getTeamMemberIds($manager);

        $assignments = ComplianceAssignment::query()
            ->whereIn('employee_id', $teamMemberIds)
            ->overdue()
            ->with(['complianceCourse.course', 'employee'])
            ->orderBy('due_date')
            ->get();

        return ComplianceAssignmentResource::collection($assignments);
    }

    /**
     * Get upcoming due assignments for the team.
     */
    public function upcoming(Request $request): AnonymousResourceCollection
    {
        $manager = auth()->user()->employee;

        if (! $manager) {
            abort(403, 'No employee record found.');
        }

        $days = $request->input('days', 7);
        $teamMemberIds = $this->getTeamMemberIds($manager);

        $assignments = ComplianceAssignment::query()
            ->whereIn('employee_id', $teamMemberIds)
            ->dueSoon($days)
            ->with(['complianceCourse.course', 'employee'])
            ->orderBy('due_date')
            ->get();

        return ComplianceAssignmentResource::collection($assignments);
    }

    /**
     * Get team member IDs for the manager.
     *
     * @return array<int>
     */
    protected function getTeamMemberIds(Employee $manager): array
    {
        return $manager->subordinates()
            ->active()
            ->pluck('id')
            ->toArray();
    }
}
