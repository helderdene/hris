<?php

namespace App\Http\Controllers;

use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Models\OnboardingTemplate;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingTemplatePageController extends Controller
{
    /**
     * Display the list of onboarding templates.
     */
    public function index(): Response
    {
        Gate::authorize('can-manage-organization');

        $templates = OnboardingTemplate::query()
            ->withCount('items')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn ($template) => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'is_default' => $template->is_default,
                'is_active' => $template->is_active,
                'items_count' => $template->items_count,
                'created_at' => $template->created_at?->format('M d, Y'),
            ]);

        return Inertia::render('Onboarding/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show the form for creating a new template.
     */
    public function create(): Response
    {
        Gate::authorize('can-manage-organization');

        return Inertia::render('Onboarding/Templates/Form', [
            'template' => null,
            'categories' => OnboardingCategory::options(),
            'roles' => OnboardingAssignedRole::options(),
        ]);
    }

    /**
     * Show the form for editing a template.
     */
    public function edit(string $tenant, OnboardingTemplate $template): Response
    {
        Gate::authorize('can-manage-organization');

        $template->load('items');

        return Inertia::render('Onboarding/Templates/Form', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'is_default' => $template->is_default,
                'is_active' => $template->is_active,
                'items' => $template->items->map(fn ($item) => [
                    'id' => $item->id,
                    'category' => $item->category->value,
                    'category_label' => $item->category->label(),
                    'name' => $item->name,
                    'description' => $item->description,
                    'assigned_role' => $item->assigned_role->value,
                    'assigned_role_label' => $item->assigned_role->label(),
                    'is_required' => $item->is_required,
                    'sort_order' => $item->sort_order,
                    'due_days_offset' => $item->due_days_offset,
                ])->toArray(),
            ],
            'categories' => OnboardingCategory::options(),
            'roles' => OnboardingAssignedRole::options(),
        ]);
    }
}
