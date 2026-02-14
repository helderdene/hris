<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Enums\OnboardingItemStatus;
use App\Enums\OnboardingStatus;
use App\Models\OnboardingChecklist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingPageController extends Controller
{
    /**
     * Display the list of all onboarding checklists for HR.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        $query = OnboardingChecklist::query()
            ->with([
                'employee',
                'items',
                'template',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        $checklists = $query->paginate(25);

        // Get summary counts
        $summaryQuery = OnboardingChecklist::query();
        $summary = [
            'pending' => (clone $summaryQuery)->where('status', OnboardingStatus::Pending)->count(),
            'in_progress' => (clone $summaryQuery)->where('status', OnboardingStatus::InProgress)->count(),
            'completed' => (clone $summaryQuery)->where('status', OnboardingStatus::Completed)->count(),
            'overdue' => (clone $summaryQuery)->where('status', OnboardingStatus::Overdue)->count(),
        ];

        return Inertia::render('Onboarding/Index', [
            'checklists' => [
                'data' => $checklists->map(fn ($checklist) => [
                    'id' => $checklist->id,
                    'status' => $checklist->status->value,
                    'status_label' => $checklist->status->label(),
                    'status_color' => $checklist->status->color(),
                    'start_date' => $checklist->start_date?->format('M d, Y'),
                    'completed_at' => $checklist->completed_at?->format('M d, Y'),
                    'progress_percentage' => $checklist->progress_percentage,
                    'employee_name' => $checklist->employee?->full_name,
                    'employee_number' => $checklist->employee?->employee_number,
                    'employee_email' => $checklist->employee?->email,
                    'department' => $checklist->employee?->department?->name,
                    'position' => $checklist->employee?->position?->title,
                    'template_name' => $checklist->template?->name,
                    'total_items' => $checklist->total_count,
                    'completed_items' => $checklist->completed_count,
                    'created_at' => $checklist->created_at?->format('M d, Y'),
                ])->toArray(),
                'links' => $checklists->linkCollection()->toArray(),
                'meta' => [
                    'current_page' => $checklists->currentPage(),
                    'last_page' => $checklists->lastPage(),
                    'total' => $checklists->total(),
                ],
            ],
            'filters' => [
                'status' => $request->input('status'),
                'search' => $request->input('search'),
            ],
            'statuses' => OnboardingStatus::options(),
            'summary' => $summary,
        ]);
    }

    /**
     * Display a specific onboarding checklist for HR review.
     */
    public function show(OnboardingChecklist $checklist): Response
    {
        Gate::authorize('can-manage-organization');

        $checklist->load([
            'employee.department',
            'employee.position',
            'template',
            'items.assignee',
            'items.completedByUser',
        ]);

        return Inertia::render('Onboarding/Show', [
            'checklist' => [
                'id' => $checklist->id,
                'status' => $checklist->status->value,
                'status_label' => $checklist->status->label(),
                'status_color' => $checklist->status->color(),
                'start_date' => $checklist->start_date?->format('M d, Y'),
                'completed_at' => $checklist->completed_at?->format('M d, Y H:i'),
                'progress_percentage' => $checklist->progress_percentage,
                'employee' => [
                    'id' => $checklist->employee?->id,
                    'full_name' => $checklist->employee?->full_name,
                    'employee_number' => $checklist->employee?->employee_number,
                    'email' => $checklist->employee?->email,
                    'department' => $checklist->employee?->department?->name,
                    'position' => $checklist->employee?->position?->title,
                    'hire_date' => $checklist->employee?->hire_date?->format('M d, Y'),
                ],
                'template_name' => $checklist->template?->name,
                'created_at' => $checklist->created_at?->format('M d, Y'),
                'items' => $checklist->items->map(fn ($item) => [
                    'id' => $item->id,
                    'category' => $item->category->value,
                    'category_label' => $item->category->label(),
                    'category_icon' => $item->category->icon(),
                    'name' => $item->name,
                    'description' => $item->description,
                    'assigned_role' => $item->assigned_role->value,
                    'assigned_role_label' => $item->assigned_role->label(),
                    'assigned_role_color' => $item->assigned_role->color(),
                    'assigned_to' => $item->assignee ? [
                        'id' => $item->assignee->id,
                        'name' => $item->assignee->name,
                    ] : null,
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
                        'items' => $items->values()->toArray(),
                    ])
                    ->values()
                    ->toArray(),
            ],
            'itemStatuses' => OnboardingItemStatus::options(),
            'categories' => OnboardingCategory::options(),
            'roles' => OnboardingAssignedRole::options(),
        ]);
    }
}
