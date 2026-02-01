<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplianceAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceCertificate;
use App\Models\ComplianceCourse;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ComplianceDashboardController extends Controller
{
    /**
     * Get overall compliance dashboard statistics.
     */
    public function index(): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $stats = [
            'overview' => $this->getOverviewStats(),
            'status_breakdown' => $this->getStatusBreakdown(),
            'department_compliance' => $this->getDepartmentCompliance(),
            'course_stats' => $this->getCourseStats(),
            'recent_completions' => $this->getRecentCompletions(),
            'upcoming_due' => $this->getUpcomingDue(),
            'overdue_assignments' => $this->getOverdueAssignments(),
            'expiring_certificates' => $this->getExpiringCertificates(),
        ];

        return response()->json($stats);
    }

    /**
     * Get overview statistics.
     */
    protected function getOverviewStats(): array
    {
        $totalAssignments = ComplianceAssignment::count();
        $activeAssignments = ComplianceAssignment::active()->count();
        $completedAssignments = ComplianceAssignment::completed()->count();
        $overdueAssignments = ComplianceAssignment::overdue()->count();

        $complianceRate = $totalAssignments > 0
            ? round(($completedAssignments / $totalAssignments) * 100, 2)
            : 0;

        return [
            'total_assignments' => $totalAssignments,
            'active_assignments' => $activeAssignments,
            'completed_assignments' => $completedAssignments,
            'overdue_assignments' => $overdueAssignments,
            'compliance_rate' => $complianceRate,
            'total_courses' => ComplianceCourse::count(),
            'total_certificates' => ComplianceCertificate::valid()->count(),
        ];
    }

    /**
     * Get assignment status breakdown.
     */
    protected function getStatusBreakdown(): array
    {
        return collect(ComplianceAssignmentStatus::cases())
            ->mapWithKeys(function ($status) {
                return [$status->value => ComplianceAssignment::byStatus($status)->count()];
            })
            ->toArray();
    }

    /**
     * Get compliance rate by department.
     */
    protected function getDepartmentCompliance(): array
    {
        return Department::query()
            ->withCount(['employees' => function ($query) {
                $query->active();
            }])
            ->get()
            ->map(function ($department) {
                $employeeIds = $department->employees()->active()->pluck('id');

                $totalAssignments = ComplianceAssignment::whereIn('employee_id', $employeeIds)->count();
                $completedAssignments = ComplianceAssignment::whereIn('employee_id', $employeeIds)
                    ->completed()
                    ->count();

                $complianceRate = $totalAssignments > 0
                    ? round(($completedAssignments / $totalAssignments) * 100, 2)
                    : 100;

                return [
                    'id' => $department->id,
                    'name' => $department->name,
                    'employee_count' => $department->employees_count,
                    'total_assignments' => $totalAssignments,
                    'completed_assignments' => $completedAssignments,
                    'overdue_assignments' => ComplianceAssignment::whereIn('employee_id', $employeeIds)
                        ->overdue()
                        ->count(),
                    'compliance_rate' => $complianceRate,
                ];
            })
            ->sortByDesc('overdue_assignments')
            ->values()
            ->toArray();
    }

    /**
     * Get statistics per compliance course.
     */
    protected function getCourseStats(): array
    {
        return ComplianceCourse::query()
            ->with('course')
            ->withCount([
                'assignments',
                'assignments as completed_count' => function ($query) {
                    $query->where('status', ComplianceAssignmentStatus::Completed);
                },
                'assignments as overdue_count' => function ($query) {
                    $query->where('status', ComplianceAssignmentStatus::Overdue);
                },
            ])
            ->get()
            ->map(function ($course) {
                $complianceRate = $course->assignments_count > 0
                    ? round(($course->completed_count / $course->assignments_count) * 100, 2)
                    : 0;

                return [
                    'id' => $course->id,
                    'title' => $course->course->title,
                    'code' => $course->course->code,
                    'total_assignments' => $course->assignments_count,
                    'completed' => $course->completed_count,
                    'overdue' => $course->overdue_count,
                    'compliance_rate' => $complianceRate,
                ];
            })
            ->sortByDesc('overdue')
            ->values()
            ->toArray();
    }

    /**
     * Get recent completions.
     */
    protected function getRecentCompletions(int $limit = 10): array
    {
        return ComplianceAssignment::query()
            ->completed()
            ->with(['employee', 'complianceCourse.course'])
            ->orderByDesc('completed_at')
            ->limit($limit)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'employee_name' => $assignment->employee->full_name,
                    'employee_id' => $assignment->employee_id,
                    'course_title' => $assignment->complianceCourse->course->title,
                    'completed_at' => $assignment->completed_at?->toISOString(),
                    'score' => $assignment->final_score,
                ];
            })
            ->toArray();
    }

    /**
     * Get assignments due soon.
     */
    protected function getUpcomingDue(int $days = 7, int $limit = 10): array
    {
        return ComplianceAssignment::query()
            ->dueSoon($days)
            ->with(['employee', 'complianceCourse.course'])
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'employee_name' => $assignment->employee->full_name,
                    'employee_id' => $assignment->employee_id,
                    'course_title' => $assignment->complianceCourse->course->title,
                    'due_date' => $assignment->due_date->toDateString(),
                    'days_until_due' => $assignment->getDaysUntilDue(),
                    'status' => $assignment->status->value,
                ];
            })
            ->toArray();
    }

    /**
     * Get overdue assignments.
     */
    protected function getOverdueAssignments(int $limit = 20): array
    {
        return ComplianceAssignment::query()
            ->overdue()
            ->with(['employee.department', 'complianceCourse.course'])
            ->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(function ($assignment) {
                return [
                    'id' => $assignment->id,
                    'employee_name' => $assignment->employee->full_name,
                    'employee_id' => $assignment->employee_id,
                    'department' => $assignment->employee->department?->name,
                    'course_title' => $assignment->complianceCourse->course->title,
                    'due_date' => $assignment->due_date->toDateString(),
                    'days_overdue' => abs($assignment->getDaysUntilDue()),
                ];
            })
            ->toArray();
    }

    /**
     * Get certificates expiring soon.
     */
    protected function getExpiringCertificates(int $days = 30, int $limit = 10): array
    {
        return ComplianceCertificate::query()
            ->expiringSoon($days)
            ->with(['complianceAssignment.employee', 'complianceAssignment.complianceCourse.course'])
            ->orderBy('valid_until')
            ->limit($limit)
            ->get()
            ->map(function ($certificate) {
                $assignment = $certificate->complianceAssignment;

                return [
                    'id' => $certificate->id,
                    'certificate_number' => $certificate->certificate_number,
                    'employee_name' => $assignment->employee->full_name,
                    'employee_id' => $assignment->employee_id,
                    'course_title' => $assignment->complianceCourse->course->title,
                    'valid_until' => $certificate->valid_until->toDateString(),
                    'days_until_expiry' => $certificate->getDaysUntilExpiration(),
                ];
            })
            ->toArray();
    }

    /**
     * Get compliance trends over time.
     */
    public function trends(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $months = $request->input('months', 6);

        $trends = collect();
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $startOfMonth = $date->copy()->startOfMonth();
            $endOfMonth = $date->copy()->endOfMonth();

            $completed = ComplianceAssignment::query()
                ->whereBetween('completed_at', [$startOfMonth, $endOfMonth])
                ->count();

            $overdue = ComplianceAssignment::query()
                ->where('due_date', '<', $endOfMonth)
                ->where('due_date', '>=', $startOfMonth)
                ->whereIn('status', [
                    ComplianceAssignmentStatus::Overdue->value,
                    ComplianceAssignmentStatus::Pending->value,
                    ComplianceAssignmentStatus::InProgress->value,
                ])
                ->count();

            $trends->push([
                'month' => $date->format('Y-m'),
                'month_label' => $date->format('M Y'),
                'completed' => $completed,
                'overdue' => $overdue,
            ]);
        }

        return response()->json($trends);
    }

    /**
     * Get employees with compliance issues.
     */
    public function employeesWithIssues(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $employees = Employee::query()
            ->active()
            ->whereHas('complianceAssignments', function ($query) {
                $query->overdue();
            })
            ->withCount([
                'complianceAssignments as overdue_count' => function ($query) {
                    $query->overdue();
                },
                'complianceAssignments as pending_count' => function ($query) {
                    $query->pending();
                },
            ])
            ->with('department', 'position')
            ->orderByDesc('overdue_count')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $employees->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->title,
                    'overdue_count' => $employee->overdue_count,
                    'pending_count' => $employee->pending_count,
                ];
            }),
            'meta' => [
                'current_page' => $employees->currentPage(),
                'last_page' => $employees->lastPage(),
                'per_page' => $employees->perPage(),
                'total' => $employees->total(),
            ],
        ]);
    }
}
