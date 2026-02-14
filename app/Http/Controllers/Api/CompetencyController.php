<?php

namespace App\Http\Controllers\Api;

use App\Enums\CompetencyCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCompetencyRequest;
use App\Http\Requests\UpdateCompetencyRequest;
use App\Http\Resources\CompetencyResource;
use App\Models\Competency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CompetencyController extends Controller
{
    /**
     * Display a listing of competencies.
     *
     * Supports filtering by is_active, category, and search.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = Competency::query()
            ->withCount('positionCompetencies')
            ->orderBy('category')
            ->orderBy('name');

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by category
        if ($request->filled('category')) {
            $category = CompetencyCategory::tryFrom($request->input('category'));
            if ($category) {
                $query->byCategory($category);
            }
        }

        // Search by name or code
        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        return CompetencyResource::collection($query->get());
    }

    /**
     * Store a newly created competency.
     */
    public function store(StoreCompetencyRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $competency = Competency::create($request->validated());

        return (new CompetencyResource($competency))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified competency.
     */
    public function show(Competency $competency): CompetencyResource
    {
        Gate::authorize('can-manage-organization');

        $competency->loadCount('positionCompetencies');

        return new CompetencyResource($competency);
    }

    /**
     * Update the specified competency.
     */
    public function update(
        UpdateCompetencyRequest $request,
        Competency $competency
    ): CompetencyResource {
        Gate::authorize('can-manage-organization');

        $competency->update($request->validated());

        $competency->loadCount('positionCompetencies');

        return new CompetencyResource($competency);
    }

    /**
     * Remove the specified competency.
     *
     * Cannot delete if there are position assignments using this competency.
     */
    public function destroy(Competency $competency): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Check if there are position competency assignments
        $assignmentsCount = $competency->positionCompetencies()->count();

        if ($assignmentsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete this competency because it is assigned to positions. Remove the assignments first or deactivate the competency.',
            ], 422);
        }

        // Soft delete the competency
        $competency->delete();

        return response()->json([
            'message' => 'Competency deleted successfully.',
        ]);
    }
}
