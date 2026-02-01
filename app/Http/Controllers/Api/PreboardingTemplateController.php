<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePreboardingTemplateRequest;
use App\Models\PreboardingTemplate;
use App\Models\PreboardingTemplateItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PreboardingTemplateController extends Controller
{
    /**
     * List all preboarding templates.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $templates = PreboardingTemplate::query()
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
     * Show a preboarding template with items.
     */
    public function show(string $tenant, PreboardingTemplate $preboardingTemplate): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $preboardingTemplate->load('items');

        return response()->json([
            'data' => [
                'id' => $preboardingTemplate->id,
                'name' => $preboardingTemplate->name,
                'description' => $preboardingTemplate->description,
                'is_default' => $preboardingTemplate->is_default,
                'is_active' => $preboardingTemplate->is_active,
                'items' => $preboardingTemplate->items->map(fn ($item) => [
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
        ]);
    }

    /**
     * Create a new preboarding template.
     */
    public function store(StorePreboardingTemplateRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $template = DB::transaction(function () use ($data) {
            if (! empty($data['is_default'])) {
                PreboardingTemplate::where('is_default', true)->update(['is_default' => false]);
            }

            $template = PreboardingTemplate::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
                'created_by' => auth()->id(),
            ]);

            if (! empty($data['items'])) {
                foreach ($data['items'] as $itemData) {
                    PreboardingTemplateItem::create(array_merge($itemData, [
                        'preboarding_template_id' => $template->id,
                    ]));
                }
            }

            return $template;
        });

        return response()->json([
            'message' => 'Template created successfully.',
            'data' => ['id' => $template->id],
        ], 201);
    }

    /**
     * Update a preboarding template.
     */
    public function update(StorePreboardingTemplateRequest $request, string $tenant, PreboardingTemplate $preboardingTemplate): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        DB::transaction(function () use ($data, $preboardingTemplate) {
            if (! empty($data['is_default'])) {
                PreboardingTemplate::where('id', '!=', $preboardingTemplate->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $preboardingTemplate->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'is_default' => $data['is_default'] ?? $preboardingTemplate->is_default,
                'is_active' => $data['is_active'] ?? $preboardingTemplate->is_active,
            ]);

            if (isset($data['items'])) {
                $preboardingTemplate->items()->delete();
                foreach ($data['items'] as $itemData) {
                    PreboardingTemplateItem::create(array_merge($itemData, [
                        'preboarding_template_id' => $preboardingTemplate->id,
                    ]));
                }
            }
        });

        return response()->json(['message' => 'Template updated successfully.']);
    }

    /**
     * Delete a preboarding template.
     */
    public function destroy(string $tenant, PreboardingTemplate $preboardingTemplate): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $preboardingTemplate->delete();

        return response()->json(['message' => 'Template deleted successfully.']);
    }
}
