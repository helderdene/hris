<?php

namespace App\Http\Controllers\My;

use App\Enums\OnboardingCategory;
use App\Enums\OnboardingItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OnboardingChecklist;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyOnboardingController extends Controller
{
    /**
     * Display the employee's onboarding dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $checklist = null;

        if ($user) {
            // Find employee via user
            $employee = Employee::where('user_id', $user->id)->first();

            if ($employee) {
                // Find active onboarding checklist for this employee
                $checklist = OnboardingChecklist::query()
                    ->where('employee_id', $employee->id)
                    ->with([
                        'items' => function ($q) {
                            $q->orderBy('category')->orderBy('sort_order');
                        },
                        'items.assignee',
                        'items.completedByUser',
                        'template',
                    ])
                    ->latest()
                    ->first();
            }
        }

        $checklistData = null;
        if ($checklist) {
            $checklistData = [
                'id' => $checklist->id,
                'status' => $checklist->status->value,
                'status_label' => $checklist->status->label(),
                'status_color' => $checklist->status->color(),
                'start_date' => $checklist->start_date?->format('M d, Y'),
                'completed_at' => $checklist->completed_at?->format('M d, Y H:i'),
                'progress_percentage' => $checklist->progress_percentage,
                'total_items' => $checklist->total_count,
                'completed_items' => $checklist->completed_count,
                'pending_items' => $checklist->pending_count,
                'items' => $checklist->items->map(fn ($item) => [
                    'id' => $item->id,
                    'category' => $item->category->value,
                    'category_label' => $item->category->label(),
                    'category_icon' => $item->category->icon(),
                    'name' => $item->name,
                    'description' => $item->description,
                    'assigned_role' => $item->assigned_role->value,
                    'assigned_role_label' => $item->assigned_role->label(),
                    'is_required' => $item->is_required,
                    'due_date' => $item->due_date?->format('M d, Y'),
                    'is_overdue' => $item->is_overdue,
                    'status' => $item->status->value,
                    'status_label' => $item->status->label(),
                    'status_color' => $item->status->color(),
                    'notes' => $item->notes,
                    'equipment_details' => $item->equipment_details,
                    'completed_at' => $item->completed_at?->format('M d, Y H:i'),
                    'completed_by' => $item->completedByUser?->name,
                ])->toArray(),
                'items_by_category' => $checklist->items
                    ->groupBy(fn ($item) => $item->category->value)
                    ->map(fn ($items, $category) => [
                        'category' => $category,
                        'category_label' => OnboardingCategory::from($category)->label(),
                        'category_icon' => OnboardingCategory::from($category)->icon(),
                        'total' => $items->count(),
                        'completed' => $items->where('status', OnboardingItemStatus::Completed)->count(),
                        'items' => $items->map(fn ($item) => [
                            'id' => $item->id,
                            'name' => $item->name,
                            'description' => $item->description,
                            'status' => $item->status->value,
                            'status_label' => $item->status->label(),
                            'status_color' => $item->status->color(),
                            'due_date' => $item->due_date?->format('M d, Y'),
                            'is_overdue' => $item->is_overdue,
                            'completed_at' => $item->completed_at?->format('M d, Y'),
                            'equipment_details' => $item->equipment_details,
                        ])->values()->toArray(),
                    ])
                    ->values()
                    ->toArray(),
            ];
        }

        return Inertia::render('My/Onboarding/Index', [
            'checklist' => $checklistData,
            'itemStatuses' => OnboardingItemStatus::options(),
            'categories' => OnboardingCategory::options(),
        ]);
    }
}
