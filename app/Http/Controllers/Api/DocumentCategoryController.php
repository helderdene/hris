<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\DocumentCategoryResource;
use App\Models\DocumentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DocumentCategoryController extends Controller
{
    /**
     * Display a listing of all document categories.
     *
     * Returns both predefined and custom categories for the tenant.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function index(): JsonResponse
    {
        $categories = DocumentCategory::query()
            ->orderBy('is_predefined', 'desc')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => DocumentCategoryResource::collection($categories),
        ]);
    }

    /**
     * Store a newly created custom category.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        $validated = $request->validated();

        $category = DocumentCategory::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_predefined' => false,
        ]);

        return response()->json([
            'data' => new DocumentCategoryResource($category),
        ], 201);
    }

    /**
     * Remove the specified custom category.
     *
     * Predefined categories cannot be deleted.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function destroy(DocumentCategory $category): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        // Prevent deletion of predefined categories
        if ($category->is_predefined) {
            return response()->json([
                'message' => 'Predefined categories cannot be deleted.',
            ], 403);
        }

        $category->delete();

        return response()->json(null, 204);
    }
}
