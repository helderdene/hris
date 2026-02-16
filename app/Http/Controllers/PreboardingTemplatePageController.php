<?php

namespace App\Http\Controllers;

use App\Enums\PreboardingItemType;
use App\Models\DocumentCategory;
use App\Models\PreboardingTemplate;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PreboardingTemplatePageController extends Controller
{
    /**
     * Display the list of preboarding templates.
     */
    public function index(): Response
    {
        Gate::authorize('can-manage-organization');

        $templates = PreboardingTemplate::query()
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

        return Inertia::render('Preboarding/Templates/Index', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show the form for creating a new template.
     */
    public function create(): Response
    {
        Gate::authorize('can-manage-organization');

        return Inertia::render('Preboarding/Templates/Form', [
            'template' => null,
            'itemTypes' => PreboardingItemType::options(),
            'documentCategories' => DocumentCategory::query()
                ->orderBy('name')
                ->get()
                ->map(fn ($cat) => ['value' => $cat->id, 'label' => $cat->name])
                ->toArray(),
        ]);
    }

    /**
     * Show the form for editing a template.
     */
    public function edit(PreboardingTemplate $template): Response
    {
        Gate::authorize('can-manage-organization');

        $template->load('items');

        return Inertia::render('Preboarding/Templates/Form', [
            'template' => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'is_default' => $template->is_default,
                'is_active' => $template->is_active,
                'items' => $template->items->map(fn ($item) => [
                    'id' => $item->id,
                    'type' => $item->type->value,
                    'type_label' => $item->type->label(),
                    'name' => $item->name,
                    'description' => $item->description,
                    'is_required' => $item->is_required,
                    'sort_order' => $item->sort_order,
                    'document_category_id' => $item->document_category_id,
                ])->toArray(),
            ],
            'itemTypes' => PreboardingItemType::options(),
            'documentCategories' => DocumentCategory::query()
                ->orderBy('name')
                ->get()
                ->map(fn ($cat) => ['value' => $cat->id, 'label' => $cat->name])
                ->toArray(),
        ]);
    }
}
