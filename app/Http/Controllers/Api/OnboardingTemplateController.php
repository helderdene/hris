<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOnboardingTemplateRequest;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class OnboardingTemplateController extends Controller
{
    /**
     * List all onboarding templates.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $templates = OnboardingTemplate::query()
            ->withCount('items')
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
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

        return response()->json(['data' => $templates]);
    }

    /**
     * Show an onboarding template with items.
     */
    public function show(string $tenant, OnboardingTemplate $onboardingTemplate): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $onboardingTemplate->load('items');

        return response()->json([
            'data' => [
                'id' => $onboardingTemplate->id,
                'name' => $onboardingTemplate->name,
                'description' => $onboardingTemplate->description,
                'is_default' => $onboardingTemplate->is_default,
                'is_active' => $onboardingTemplate->is_active,
                'items' => $onboardingTemplate->items->map(fn ($item) => [
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
        ]);
    }

    /**
     * Create a new onboarding template.
     */
    public function store(StoreOnboardingTemplateRequest $request): JsonResponse|RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $template = DB::transaction(function () use ($data) {
            if (! empty($data['is_default'])) {
                OnboardingTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            $template = OnboardingTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => auth()->id(),
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    OnboardingTemplateItem::create(array_merge($itemData, [
                        'onboarding_template_id' => $template->id,
                    ]));
                }
            }

            return $template;
        });

        if ($request->inertia()) {
            return redirect('/onboarding-templates');
        }

        return response()->json([
            'message' => 'Template created successfully.',
            'data' => ['id' => $template->id],
        ], 201);
    }

    /**
     * Update an onboarding template.
     */
    public function update(StoreOnboardingTemplateRequest $request, string $tenant, OnboardingTemplate $onboardingTemplate): JsonResponse|RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        DB::transaction(function () use ($data, $onboardingTemplate) {
            if (! empty($data['is_default'])) {
                OnboardingTemplate::where('id', '!=', $onboardingTemplate->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $onboardingTemplate->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $data['is_default'] ?? $onboardingTemplate->is_default,
                'is_active' => $data['is_active'] ?? $onboardingTemplate->is_active,
            ]);

            if (isset($data['items'])) {
                $onboardingTemplate->items()->delete();
                foreach ($data['items'] as $itemData) {
                    OnboardingTemplateItem::create(array_merge($itemData, [
                        'onboarding_template_id' => $onboardingTemplate->id,
                    ]));
                }
            }
        });

        if ($request->inertia()) {
            return redirect('/onboarding-templates');
        }

        return response()->json(['message' => 'Template updated successfully.']);
    }

    /**
     * Delete an onboarding template.
     */
    public function destroy(Request $request, string $tenant, OnboardingTemplate $onboardingTemplate): JsonResponse|RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $onboardingTemplate->delete();

        if ($request->inertia()) {
            return redirect('/onboarding-templates');
        }

        return response()->json(['message' => 'Template deleted successfully.']);
    }

    /**
     * Toggle the active status of an onboarding template.
     */
    public function toggleActive(Request $request, string $tenant, OnboardingTemplate $onboardingTemplate): JsonResponse|RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $onboardingTemplate->update(['is_active' => ! $onboardingTemplate->is_active]);

        if ($request->inertia()) {
            return back();
        }

        return response()->json([
            'message' => 'Template status updated.',
            'is_active' => $onboardingTemplate->is_active,
        ]);
    }
}
