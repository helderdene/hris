<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Enums\OnboardingItemStatus;
use App\Models\OnboardingChecklistItem;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingTasksPageController extends Controller
{
    /**
     * Display onboarding tasks for the current user based on their role.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get tasks assigned to the user or their role
        $query = OnboardingChecklistItem::query()
            ->with([
                'checklist.employee',
                'assignee',
            ])
            ->whereHas('checklist', fn ($q) => $q->active())
            ->whereIn('status', [OnboardingItemStatus::Pending, OnboardingItemStatus::InProgress]);

        // Build role filter based on user permissions
        $userRoles = $this->getUserOnboardingRoles($user);

        // Filter by tasks directly assigned to user OR assigned to user's role
        $query->where(function ($q) use ($user, $userRoles) {
            // Tasks directly assigned to this user
            $q->where('assigned_to', $user->id);

            // Or tasks for roles user can handle (if not assigned to someone else)
            if ($userRoles->isNotEmpty()) {
                $q->orWhere(function ($roleQuery) use ($userRoles) {
                    $roleQuery->whereNull('assigned_to')
                        ->whereIn('assigned_role', $userRoles->pluck('value'));
                });
            }
        });

        // Apply filters
        if ($request->filled('category')) {
            $query->where('category', $request->input('category'));
        }

        if ($request->filled('role')) {
            $query->where('assigned_role', $request->input('role'));
        }

        $tasks = $query->orderBy('due_date')->paginate(25);

        // Group tasks by employee/checklist for display
        $groupedTasks = $tasks->getCollection()
            ->groupBy('onboarding_checklist_id')
            ->map(fn ($items) => [
                'checklist_id' => $items->first()->onboarding_checklist_id,
                'employee_name' => $items->first()->checklist?->employee?->full_name,
                'employee_number' => $items->first()->checklist?->employee?->employee_number,
                'start_date' => $items->first()->checklist?->start_date?->format('M d, Y'),
                'items' => $items->map(fn ($item) => [
                    'id' => $item->id,
                    'category' => $item->category->value,
                    'category_label' => $item->category->label(),
                    'category_icon' => $item->category->icon(),
                    'name' => $item->name,
                    'description' => $item->description,
                    'assigned_role' => $item->assigned_role->value,
                    'assigned_role_label' => $item->assigned_role->label(),
                    'assigned_role_color' => $item->assigned_role->color(),
                    'is_required' => $item->is_required,
                    'due_date' => $item->due_date?->format('M d, Y'),
                    'is_overdue' => $item->is_overdue,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'status_color' => $item->status->color(),
                ])->values()->toArray(),
            ])
            ->values();

        return Inertia::render('Onboarding/Tasks/Index', [
            'tasks' => [
                'data' => $groupedTasks->toArray(),
                'links' => $tasks->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'total' => $tasks->total(),
                ],
            ],
            'filters' => [
                'category' => $request->input('category'),
                'role' => $request->input('role'),
            ],
            'categories' => OnboardingCategory::options(),
            'roles' => OnboardingAssignedRole::options(),
            'userRoles' => $userRoles->map(fn ($role) => $role->value)->toArray(),
        ]);
    }

    /**
     * Get the onboarding roles the user can handle.
     */
    protected function getUserOnboardingRoles(User $user): \Illuminate\Support\Collection
    {
        $roles = collect();

        if ($user->can('handle-it-onboarding')) {
            $roles->push(OnboardingAssignedRole::IT);
        }

        if ($user->can('handle-admin-onboarding')) {
            $roles->push(OnboardingAssignedRole::Admin);
        }

        if ($user->can('handle-hr-onboarding')) {
            $roles->push(OnboardingAssignedRole::HR);
        }

        // Fallback: check for general organization management permission
        if ($roles->isEmpty() && $user->can('can-manage-organization')) {
            // Admins can see all roles
            return collect(OnboardingAssignedRole::cases());
        }

        return $roles;
    }
}
