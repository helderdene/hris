<?php

namespace App\Services;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingItemStatus;
use App\Enums\OnboardingStatus;
use App\Models\Employee;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingChecklistItem;
use App\Models\OnboardingTemplate;
use App\Models\User;
use App\Notifications\OnboardingCompleted;
use App\Notifications\OnboardingCreated;
use App\Notifications\OnboardingTaskAssigned;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Service for managing the onboarding lifecycle.
 */
class OnboardingService
{
    /**
     * Create an onboarding checklist from a template for a new employee.
     */
    public function createFromEmployee(Employee $employee, ?OnboardingTemplate $template = null): OnboardingChecklist
    {
        if (! $template) {
            $template = OnboardingTemplate::query()
                ->where('is_default', true)
                ->where('is_active', true)
                ->first();
        }

        // If no default template, try to get any active template
        if (! $template) {
            $template = OnboardingTemplate::query()
                ->where('is_active', true)
                ->first();
        }

        return DB::transaction(function () use ($employee, $template) {
            $startDate = $employee->hire_date ?? now();

            $checklist = OnboardingChecklist::create([
                'employee_id' => $employee->id,
                'onboarding_template_id' => $template?->id,
                'status' => OnboardingStatus::Pending,
                'start_date' => $startDate,
                'created_by' => auth()->id(),
            ]);

            if ($template) {
                foreach ($template->items()->orderBy('sort_order')->get() as $templateItem) {
                    OnboardingChecklistItem::create([
                        'onboarding_checklist_id' => $checklist->id,
                        'onboarding_template_item_id' => $templateItem->id,
                        'category' => $templateItem->category,
                        'name' => $templateItem->name,
                        'description' => $templateItem->description,
                        'assigned_role' => $templateItem->assigned_role,
                        'is_required' => $templateItem->is_required,
                        'sort_order' => $templateItem->sort_order,
                        'due_date' => $startDate->copy()->addDays($templateItem->due_days_offset),
                        'status' => OnboardingItemStatus::Pending,
                    ]);
                }
            }

            // Notify the new employee
            if ($employee->user) {
                $employee->user->notify(new OnboardingCreated($checklist));
            }

            // Notify users by role about their assigned tasks
            $this->notifyAssignedRoles($checklist);

            return $checklist->fresh('items');
        });
    }

    /**
     * Mark an item as complete.
     *
     * @param  array<string, mixed>|null  $data
     */
    public function completeItem(OnboardingChecklistItem $item, User $completedBy, ?array $data = null): OnboardingChecklistItem
    {
        return DB::transaction(function () use ($item, $completedBy, $data) {
            $updateData = [
                'status' => OnboardingItemStatus::Completed,
                'completed_at' => now(),
                'completed_by' => $completedBy->id,
            ];

            if (isset($data['notes'])) {
                $updateData['notes'] = $data['notes'];
            }

            if (isset($data['equipment_details'])) {
                $updateData['equipment_details'] = $data['equipment_details'];
            }

            $item->update($updateData);

            // Update checklist status
            $checklist = $item->checklist;
            if ($checklist->status === OnboardingStatus::Pending) {
                $checklist->update(['status' => OnboardingStatus::InProgress]);
            }

            $this->recalculateStatus($checklist);

            return $item->fresh();
        });
    }

    /**
     * Skip an optional item.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function skipItem(OnboardingChecklistItem $item, User $skippedBy, string $reason): OnboardingChecklistItem
    {
        if ($item->is_required) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'item' => 'Required items cannot be skipped.',
            ]);
        }

        return DB::transaction(function () use ($item, $skippedBy, $reason) {
            $item->update([
                'status' => OnboardingItemStatus::Skipped,
                'completed_at' => now(),
                'completed_by' => $skippedBy->id,
                'notes' => $reason,
            ]);

            $this->recalculateStatus($item->checklist);

            return $item->fresh();
        });
    }

    /**
     * Assign an item to a specific user.
     */
    public function assignItem(OnboardingChecklistItem $item, User $assignee): OnboardingChecklistItem
    {
        $item->update(['assigned_to' => $assignee->id]);

        // Notify the assignee
        $assignee->notify(new OnboardingTaskAssigned(collect([$item])));

        return $item->fresh();
    }

    /**
     * Start working on an item.
     */
    public function startItem(OnboardingChecklistItem $item): OnboardingChecklistItem
    {
        if ($item->status !== OnboardingItemStatus::Pending) {
            return $item;
        }

        return DB::transaction(function () use ($item) {
            $item->update(['status' => OnboardingItemStatus::InProgress]);

            // Update checklist status if needed
            $checklist = $item->checklist;
            if ($checklist->status === OnboardingStatus::Pending) {
                $checklist->update(['status' => OnboardingStatus::InProgress]);
            }

            return $item->fresh();
        });
    }

    /**
     * Get progress percentage for a checklist.
     */
    public function getProgressPercentage(OnboardingChecklist $checklist): int
    {
        return $checklist->progress_percentage;
    }

    /**
     * Get items for a specific role.
     *
     * @return Collection<int, OnboardingChecklistItem>
     */
    public function getItemsForRole(OnboardingChecklist $checklist, OnboardingAssignedRole $role): Collection
    {
        return $checklist->items()
            ->where('assigned_role', $role)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get all pending items across all checklists for a specific role.
     *
     * @return Collection<int, OnboardingChecklistItem>
     */
    public function getPendingItemsForRole(OnboardingAssignedRole $role): Collection
    {
        return OnboardingChecklistItem::query()
            ->with(['checklist.employee'])
            ->whereHas('checklist', fn ($q) => $q->active())
            ->where('assigned_role', $role)
            ->whereIn('status', [OnboardingItemStatus::Pending, OnboardingItemStatus::InProgress])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Get all pending items assigned to a specific user.
     *
     * @return Collection<int, OnboardingChecklistItem>
     */
    public function getPendingItemsForUser(User $user): Collection
    {
        return OnboardingChecklistItem::query()
            ->with(['checklist.employee'])
            ->whereHas('checklist', fn ($q) => $q->active())
            ->where('assigned_to', $user->id)
            ->whereIn('status', [OnboardingItemStatus::Pending, OnboardingItemStatus::InProgress])
            ->orderBy('due_date')
            ->get();
    }

    /**
     * Recalculate and update the overall checklist status.
     */
    public function recalculateStatus(OnboardingChecklist $checklist): void
    {
        $requiredItems = $checklist->items()->where('is_required', true)->get();

        if ($requiredItems->isEmpty()) {
            return;
        }

        $allCompleted = $requiredItems->every(fn ($item) => $item->status->isDone());

        if ($allCompleted) {
            $checklist->update([
                'status' => OnboardingStatus::Completed,
                'completed_at' => now(),
            ]);

            // Notify HR/creator
            $creator = $checklist->creator;
            if ($creator) {
                $creator->notify(new OnboardingCompleted($checklist));
            }
        }
    }

    /**
     * Check for overdue checklists and update their status.
     */
    public function checkOverdueChecklists(): void
    {
        $overdueChecklists = OnboardingChecklist::query()
            ->active()
            ->whereHas('items', function ($q) {
                $q->where('is_required', true)
                    ->whereDate('due_date', '<', now())
                    ->whereIn('status', [OnboardingItemStatus::Pending, OnboardingItemStatus::InProgress]);
            })
            ->get();

        foreach ($overdueChecklists as $checklist) {
            $checklist->update(['status' => OnboardingStatus::Overdue]);
        }
    }

    /**
     * Notify users by role about their assigned tasks.
     */
    protected function notifyAssignedRoles(OnboardingChecklist $checklist): void
    {
        $itemsByRole = $checklist->items->groupBy('assigned_role');

        foreach ($itemsByRole as $role => $items) {
            $roleEnum = OnboardingAssignedRole::from($role);

            // Get users with this role (implementation depends on how roles are assigned)
            $users = $this->getUsersForRole($roleEnum);

            if ($users->isNotEmpty()) {
                Notification::send($users, new OnboardingTaskAssigned($items));
            }
        }
    }

    /**
     * Get users assigned to a specific onboarding role.
     *
     * This implementation returns an empty collection by default.
     * Override this method or configure a role-to-users mapping
     * to enable automatic role-based notifications.
     *
     * @return Collection<int, User>
     */
    protected function getUsersForRole(OnboardingAssignedRole $role): Collection
    {
        // This implementation can be customized based on how roles are assigned.
        // For now, return empty collection as the app doesn't have a roles/permissions system.
        // Users can be notified individually when items are assigned to them.
        return collect();
    }
}
