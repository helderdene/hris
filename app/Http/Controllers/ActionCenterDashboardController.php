<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingAssignedRole;
use App\Enums\PriorityLevel;
use App\Enums\TenantUserRole;
use App\Models\AuditLog;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\JobRequisitionApproval;
use App\Models\LeaveApplicationApproval;
use App\Models\OnboardingChecklistItem;
use App\Models\ProbationaryEvaluationApproval;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Action Center Dashboard for HR Admins and Managers.
 *
 * Provides an interactive overview of pending approvals, priority items,
 * notifications, and recent activity.
 */
class ActionCenterDashboardController extends Controller
{
    /**
     * Display the Action Center Dashboard.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = $this->getEmployeeForUser($user);
        $role = $this->getUserRole($user);

        // Get pending action counts (fast queries)
        $pendingActions = $this->getPendingActionCounts($employee, $role);

        // Get priority items (items needing immediate attention)
        $priorityItems = $this->getPriorityItems($employee, $role);

        return Inertia::render('TenantDashboard', [
            'justCreated' => $request->query('just_created') === '1',

            // Immediate props (fast counts)
            'pendingActions' => $pendingActions,
            'priorityItems' => $priorityItems,

            // Deferred props (heavier queries)
            'notifications' => Inertia::defer(fn () => $this->getNotifications($user)),
            'unreadNotificationCount' => Inertia::defer(fn () => $user->unreadNotifications()->count()),
            'activityFeed' => Inertia::defer(fn () => $this->getActivityFeed()),
            'pendingLeaveDetails' => Inertia::defer(fn () => $this->getPendingLeaveDetails($employee)),
            'pendingRequisitionDetails' => Inertia::defer(fn () => $this->getPendingRequisitionDetails($employee)),
        ]);
    }

    /**
     * Get the employee record for the current user.
     */
    protected function getEmployeeForUser(?User $user): ?Employee
    {
        if ($user === null) {
            return null;
        }

        return Employee::where('user_id', $user->id)->first();
    }

    /**
     * Get the user's role in the current tenant.
     */
    protected function getUserRole(?User $user): ?TenantUserRole
    {
        if ($user === null) {
            return null;
        }

        $tenant = tenant();

        if ($tenant === null) {
            return null;
        }

        return $user->getRoleInTenant($tenant);
    }

    /**
     * Get counts of pending actions by category.
     *
     * @return array<string, int>
     */
    protected function getPendingActionCounts(?Employee $employee, ?TenantUserRole $role): array
    {
        $counts = [
            'leaveApprovals' => 0,
            'requisitionApprovals' => 0,
            'probationaryEvaluations' => 0,
            'documentRequests' => 0,
            'onboardingTasks' => 0,
        ];

        // Leave approvals - for the current user as approver
        if ($employee !== null) {
            $counts['leaveApprovals'] = LeaveApplicationApproval::query()
                ->pending()
                ->forApprover($employee)
                ->count();

            $counts['requisitionApprovals'] = JobRequisitionApproval::query()
                ->pending()
                ->forApprover($employee)
                ->count();

            $counts['probationaryEvaluations'] = ProbationaryEvaluationApproval::query()
                ->pending()
                ->forApprover($employee)
                ->count();
        }

        // Document requests - for HR roles
        if ($this->canManageDocumentRequests($role)) {
            $counts['documentRequests'] = DocumentRequest::query()
                ->pending()
                ->count();
        }

        // Onboarding tasks - for HR role
        if ($this->canManageOnboarding($role)) {
            $counts['onboardingTasks'] = OnboardingChecklistItem::query()
                ->pending()
                ->forRole(OnboardingAssignedRole::HR)
                ->count();
        }

        return $counts;
    }

    /**
     * Get items that need priority attention (overdue or approaching deadline).
     *
     * @return array<array<string, mixed>>
     */
    protected function getPriorityItems(?Employee $employee, ?TenantUserRole $role): array
    {
        $items = [];

        if ($employee !== null) {
            // Get overdue leave approvals
            $overdueLeaves = LeaveApplicationApproval::query()
                ->overdue()
                ->forApprover($employee)
                ->with(['leaveApplication.employee'])
                ->limit(10)
                ->get();

            foreach ($overdueLeaves as $approval) {
                $items[] = $this->formatPriorityItem($approval, 'leave_approval', PriorityLevel::Critical);
            }

            // Get approaching deadline leave approvals
            $approachingLeaves = LeaveApplicationApproval::query()
                ->approaching()
                ->forApprover($employee)
                ->with(['leaveApplication.employee'])
                ->limit(10)
                ->get();

            foreach ($approachingLeaves as $approval) {
                $items[] = $this->formatPriorityItem($approval, 'leave_approval', PriorityLevel::High);
            }

            // Get overdue job requisition approvals
            $overdueRequisitions = JobRequisitionApproval::query()
                ->overdue()
                ->forApprover($employee)
                ->with(['jobRequisition'])
                ->limit(10)
                ->get();

            foreach ($overdueRequisitions as $approval) {
                $items[] = $this->formatPriorityItem($approval, 'requisition_approval', PriorityLevel::Critical);
            }

            // Get approaching deadline requisition approvals
            $approachingRequisitions = JobRequisitionApproval::query()
                ->approaching()
                ->forApprover($employee)
                ->with(['jobRequisition'])
                ->limit(10)
                ->get();

            foreach ($approachingRequisitions as $approval) {
                $items[] = $this->formatPriorityItem($approval, 'requisition_approval', PriorityLevel::High);
            }
        }

        // Sort by priority (critical first), then by created_at
        usort($items, function ($a, $b) {
            $priorityOrder = [
                PriorityLevel::Critical->value => 0,
                PriorityLevel::High->value => 1,
                PriorityLevel::Medium->value => 2,
            ];

            $aPriority = $priorityOrder[$a['priority']] ?? 3;
            $bPriority = $priorityOrder[$b['priority']] ?? 3;

            if ($aPriority !== $bPriority) {
                return $aPriority - $bPriority;
            }

            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });

        return array_slice($items, 0, 10);
    }

    /**
     * Format a priority item for the frontend.
     *
     * @return array<string, mixed>
     */
    protected function formatPriorityItem(
        LeaveApplicationApproval|JobRequisitionApproval $approval,
        string $type,
        PriorityLevel $priority
    ): array {
        $data = [
            'id' => $approval->id,
            'type' => $type,
            'priority' => $priority->value,
            'priority_label' => $priority->label(),
            'priority_color' => $priority->color(),
            'created_at' => $approval->created_at->toISOString(),
        ];

        if ($approval instanceof LeaveApplicationApproval) {
            $leave = $approval->leaveApplication;
            $data['title'] = 'Leave Request';
            $data['employee_name'] = $leave?->employee?->full_name ?? 'Unknown';
            $totalDays = $leave?->total_days;
            $formattedDays = $totalDays == (int) $totalDays ? (int) $totalDays : $totalDays;
            $data['description'] = $leave ? "{$leave->leaveType?->name} ({$formattedDays} days)" : 'Leave request';
            $data['start_date'] = $leave?->start_date?->toDateString();
            $data['end_date'] = $leave?->end_date?->toDateString();
            $data['hours_overdue'] = $approval->hours_overdue ?? 0;
            $data['hours_remaining'] = $approval->hours_remaining ?? 0;
            $data['link'] = "/leave/applications/{$leave?->id}";
        } else {
            $requisition = $approval->jobRequisition;
            $data['title'] = 'Job Requisition';
            $data['employee_name'] = $requisition?->requestedBy?->full_name ?? 'Unknown';
            $data['description'] = $requisition?->position?->name ?? 'New Position';
            $data['hours_overdue'] = $approval->hours_overdue ?? 0;
            $data['hours_remaining'] = $approval->hours_remaining ?? 0;
            $data['link'] = "/recruitment/requisitions/{$requisition?->id}";
        }

        return $data;
    }

    /**
     * Get user notifications.
     *
     * @return array<array<string, mixed>>
     */
    protected function getNotifications(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $user->notifications()
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn ($notification) => [
                'id' => $notification->id,
                'type' => class_basename($notification->type),
                'data' => $notification->data,
                'read_at' => $notification->read_at?->toISOString(),
                'created_at' => $notification->created_at->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Get activity feed from audit logs.
     *
     * @return array<array<string, mixed>>
     */
    protected function getActivityFeed(): array
    {
        return AuditLog::query()
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action->value,
                'action_label' => $log->action->label(),
                'action_color' => $log->action->color(),
                'model_name' => $log->model_name,
                'user_name' => $log->user_name,
                'created_at' => $log->created_at->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Get detailed pending leave approvals for inline actions.
     *
     * @return array<array<string, mixed>>
     */
    protected function getPendingLeaveDetails(?Employee $employee): array
    {
        if ($employee === null) {
            return [];
        }

        return LeaveApplicationApproval::query()
            ->pending()
            ->forApprover($employee)
            ->with(['leaveApplication.employee', 'leaveApplication.leaveType'])
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn (LeaveApplicationApproval $approval) => [
                'id' => $approval->id,
                'leave_application_id' => $approval->leave_application_id,
                'employee_name' => $approval->leaveApplication?->employee?->full_name,
                'employee_id' => $approval->leaveApplication?->employee_id,
                'leave_type' => $approval->leaveApplication?->leaveType?->name,
                'start_date' => $approval->leaveApplication?->start_date?->toDateString(),
                'end_date' => $approval->leaveApplication?->end_date?->toDateString(),
                'total_days' => $approval->leaveApplication?->total_days,
                'reason' => $approval->leaveApplication?->reason,
                'is_overdue' => $approval->is_overdue,
                'is_approaching_deadline' => $approval->is_approaching_deadline,
                'priority_level' => $approval->priority_level?->value,
                'hours_remaining' => $approval->hours_remaining,
                'hours_overdue' => $approval->hours_overdue,
                'created_at' => $approval->created_at->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Get detailed pending job requisition approvals for inline actions.
     *
     * @return array<array<string, mixed>>
     */
    protected function getPendingRequisitionDetails(?Employee $employee): array
    {
        if ($employee === null) {
            return [];
        }

        return JobRequisitionApproval::query()
            ->pending()
            ->forApprover($employee)
            ->with(['jobRequisition.position', 'jobRequisition.department', 'jobRequisition.requestedBy'])
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn (JobRequisitionApproval $approval) => [
                'id' => $approval->id,
                'job_requisition_id' => $approval->job_requisition_id,
                'position_name' => $approval->jobRequisition?->position?->name,
                'department_name' => $approval->jobRequisition?->department?->name,
                'requested_by' => $approval->jobRequisition?->requestedBy?->full_name,
                'number_of_positions' => $approval->jobRequisition?->number_of_positions,
                'justification' => $approval->jobRequisition?->justification,
                'is_overdue' => $approval->is_overdue,
                'is_approaching_deadline' => $approval->is_approaching_deadline,
                'priority_level' => $approval->priority_level?->value,
                'hours_remaining' => $approval->hours_remaining,
                'hours_overdue' => $approval->hours_overdue,
                'created_at' => $approval->created_at->toISOString(),
            ])
            ->toArray();
    }

    /**
     * Check if user can manage document requests.
     */
    protected function canManageDocumentRequests(?TenantUserRole $role): bool
    {
        if ($role === null) {
            return false;
        }

        return in_array($role, [
            TenantUserRole::Admin,
            TenantUserRole::HrManager,
            TenantUserRole::HrStaff,
        ], true);
    }

    /**
     * Check if user can manage onboarding.
     */
    protected function canManageOnboarding(?TenantUserRole $role): bool
    {
        if ($role === null) {
            return false;
        }

        return in_array($role, [
            TenantUserRole::Admin,
            TenantUserRole::HrManager,
            TenantUserRole::HrStaff,
        ], true);
    }
}
