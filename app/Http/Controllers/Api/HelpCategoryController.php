<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHelpCategoryRequest;
use App\Http\Requests\UpdateHelpCategoryRequest;
use App\Http\Resources\HelpCategoryResource;
use App\Models\HelpCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class HelpCategoryController extends Controller
{
    /**
     * Display a listing of help categories.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('is-super-admin');

        $query = HelpCategory::query()
            ->withCount('articles')
            ->orderBy('sort_order');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return HelpCategoryResource::collection($query->get());
    }

    /**
     * Store a newly created help category.
     */
    public function store(StoreHelpCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (! isset($data['sort_order'])) {
            $data['sort_order'] = HelpCategory::max('sort_order') + 1;
        }

        $category = HelpCategory::create($data);

        return (new HelpCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified help category.
     */
    public function show(HelpCategory $category): HelpCategoryResource
    {
        Gate::authorize('is-super-admin');

        return new HelpCategoryResource($category->loadCount('articles'));
    }

    /**
     * Update the specified help category.
     */
    public function update(UpdateHelpCategoryRequest $request, HelpCategory $category): HelpCategoryResource
    {
        $category->update($request->validated());

        return new HelpCategoryResource($category->loadCount('articles'));
    }

    /**
     * Remove the specified help category.
     */
    public function destroy(HelpCategory $category): JsonResponse
    {
        Gate::authorize('is-super-admin');

        // Check if category has articles
        if ($category->articles()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete category with articles. Please move or delete the articles first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Help category deleted successfully.',
        ]);
    }
}
