<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePerformanceCycleRequest;
use App\Http\Requests\UpdatePerformanceCycleRequest;
use App\Http\Resources\PerformanceCycleResource;
use App\Models\PerformanceCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PerformanceCycleController extends Controller
{
    /**
     * Display a listing of performance cycles.
     *
     * Supports filtering by status and cycle_type.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = PerformanceCycle::query()
            ->withCount('performanceCycleInstances')
            ->orderBy('is_default', 'desc')
            ->orderBy('name');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by cycle type
        if ($request->filled('cycle_type')) {
            $query->where('cycle_type', $request->input('cycle_type'));
        }

        return PerformanceCycleResource::collection($query->get());
    }

    /**
     * Store a newly created performance cycle.
     */
    public function store(StorePerformanceCycleRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $cycle = PerformanceCycle::create($data);

        // If this is set as default, update others
        if ($cycle->is_default) {
            $cycle->setAsDefault();
        }

        return (new PerformanceCycleResource($cycle))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified performance cycle.
     */
    public function show(PerformanceCycle $performanceCycle): PerformanceCycleResource
    {
        Gate::authorize('can-manage-organization');

        $performanceCycle->loadCount('performanceCycleInstances');

        return new PerformanceCycleResource($performanceCycle);
    }

    /**
     * Update the specified performance cycle.
     */
    public function update(
        UpdatePerformanceCycleRequest $request,
        PerformanceCycle $performanceCycle
    ): PerformanceCycleResource {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $performanceCycle->update($data);

        // If this is set as default, update others
        if (isset($data['is_default']) && $data['is_default']) {
            $performanceCycle->setAsDefault();
        }

        $performanceCycle->loadCount('performanceCycleInstances');

        return new PerformanceCycleResource($performanceCycle);
    }

    /**
     * Remove the specified performance cycle.
     *
     * Cannot delete if there are associated instances that are not in draft status.
     */
    public function destroy(PerformanceCycle $performanceCycle): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Check if there are non-draft instances
        $nonDraftInstances = $performanceCycle->performanceCycleInstances()
            ->where('status', '!=', 'draft')
            ->count();

        if ($nonDraftInstances > 0) {
            return response()->json([
                'message' => 'Cannot delete this cycle because it has instances that are not in draft status.',
            ], 422);
        }

        // Delete all draft instances first
        $performanceCycle->performanceCycleInstances()->delete();

        // Delete the cycle
        $performanceCycle->delete();

        return response()->json([
            'message' => 'Performance cycle deleted successfully.',
        ]);
    }
}
