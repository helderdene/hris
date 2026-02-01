<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseCategoryRequest;
use App\Http\Requests\UpdateCourseCategoryRequest;
use App\Http\Resources\CourseCategoryResource;
use App\Models\CourseCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CourseCategoryController extends Controller
{
    /**
     * Display a listing of course categories.
     *
     * Supports filtering by is_active and search.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-view-training');

        $query = CourseCategory::query()
            ->with(['parent', 'children'])
            ->withCount('courses')
            ->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->boolean('root_only')) {
            $query->root();
        }

        return CourseCategoryResource::collection($query->get());
    }

    /**
     * Store a newly created course category.
     */
    public function store(StoreCourseCategoryRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $validated['is_active'] = $validated['is_active'] ?? true;

        $category = CourseCategory::create($validated);

        $category->load(['parent', 'children']);

        return (new CourseCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified course category.
     */
    public function show(string $tenant, CourseCategory $category): CourseCategoryResource
    {
        Gate::authorize('can-view-training');

        $category->load(['parent', 'children']);
        $category->loadCount('courses');

        return new CourseCategoryResource($category);
    }

    /**
     * Update the specified course category.
     */
    public function update(
        UpdateCourseCategoryRequest $request,
        string $tenant,
        CourseCategory $category
    ): CourseCategoryResource {
        Gate::authorize('can-manage-training');

        $category->update($request->validated());

        $category->load(['parent', 'children']);
        $category->loadCount('courses');

        return new CourseCategoryResource($category);
    }

    /**
     * Remove the specified course category.
     *
     * Cannot delete if there are courses in this category.
     */
    public function destroy(string $tenant, CourseCategory $category): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $coursesCount = $category->courses()->count();

        if ($coursesCount > 0) {
            return response()->json([
                'message' => 'Cannot delete this category because it has courses assigned. Remove the courses first or deactivate the category.',
            ], 422);
        }

        $childrenCount = $category->children()->count();

        if ($childrenCount > 0) {
            return response()->json([
                'message' => 'Cannot delete this category because it has child categories. Remove the child categories first.',
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Course category deleted successfully.',
        ]);
    }
}
