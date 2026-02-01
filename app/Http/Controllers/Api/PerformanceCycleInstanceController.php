<?php

namespace App\Http\Controllers\Api;

use App\Enums\PerformanceCycleInstanceStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePerformanceCycleInstancesRequest;
use App\Http\Requests\StorePerformanceCycleInstanceRequest;
use App\Http\Requests\UpdatePerformanceCycleInstanceRequest;
use App\Http\Requests\UpdatePerformanceCycleInstanceStatusRequest;
use App\Http\Resources\PerformanceCycleInstanceResource;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Services\PerformanceCycleInstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PerformanceCycleInstanceController extends Controller
{
    public function __construct(
        protected PerformanceCycleInstanceService $instanceService
    ) {}

    /**
     * Display a listing of performance cycle instances.
     *
     * Supports filtering by year, cycle_id, and status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = PerformanceCycleInstance::query()
            ->with('performanceCycle')
            ->orderBy('year', 'desc')
            ->orderBy('instance_number');

        // Filter by year
        if ($request->filled('year')) {
            $query->forYear((int) $request->input('year'));
        }

        // Filter by cycle
        if ($request->filled('cycle_id')) {
            $query->forCycle((int) $request->input('cycle_id'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = PerformanceCycleInstanceStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->byStatus($status);
            }
        }

        return PerformanceCycleInstanceResource::collection($query->get());
    }

    /**
     * Store a newly created performance cycle instance.
     */
    public function store(StorePerformanceCycleInstanceRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $instance = PerformanceCycleInstance::create($data);

        return (new PerformanceCycleInstanceResource($instance->load('performanceCycle')))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Generate instances for a cycle and year.
     */
    public function generate(GeneratePerformanceCycleInstancesRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $cycle = PerformanceCycle::findOrFail($data['performance_cycle_id']);

        if (! $cycle->isRecurring()) {
            return response()->json([
                'message' => "Cannot generate recurring instances for {$cycle->cycle_type->label()} cycles. Please create instances manually.",
            ], 422);
        }

        $instances = $this->instanceService->generateInstancesForYear(
            $cycle,
            (int) $data['year'],
            (bool) ($data['overwrite'] ?? false)
        );

        $count = $instances->count();

        // Eager load the performanceCycle relationship for each instance
        $instances->each(function ($instance) {
            $instance->load('performanceCycle');
        });

        return response()->json([
            'message' => "Generated {$count} instance(s) for {$cycle->name} in {$data['year']}.",
            'count' => $count,
            'instances' => PerformanceCycleInstanceResource::collection($instances),
        ]);
    }

    /**
     * Display the specified performance cycle instance.
     */
    public function show(string $tenant, PerformanceCycleInstance $performanceCycleInstance): PerformanceCycleInstanceResource
    {
        Gate::authorize('can-manage-organization');

        return new PerformanceCycleInstanceResource($performanceCycleInstance->load('performanceCycle'));
    }

    /**
     * Update the specified performance cycle instance.
     */
    public function update(
        UpdatePerformanceCycleInstanceRequest $request,
        string $tenant,
        PerformanceCycleInstance $performanceCycleInstance
    ): PerformanceCycleInstanceResource {
        Gate::authorize('can-manage-organization');

        if (! $performanceCycleInstance->isEditable()) {
            abort(422, 'This instance cannot be edited in its current status.');
        }

        $data = $request->validated();

        $performanceCycleInstance->update($data);

        return new PerformanceCycleInstanceResource($performanceCycleInstance->load('performanceCycle'));
    }

    /**
     * Update the status of a performance cycle instance.
     */
    public function updateStatus(
        UpdatePerformanceCycleInstanceStatusRequest $request,
        string $tenant,
        PerformanceCycleInstance $performanceCycleInstance
    ): PerformanceCycleInstanceResource {
        Gate::authorize('can-manage-organization');

        $newStatus = PerformanceCycleInstanceStatus::from($request->validated('status'));

        if (! $performanceCycleInstance->canTransitionTo($newStatus)) {
            abort(422, "Cannot transition from {$performanceCycleInstance->status->label()} to {$newStatus->label()}.");
        }

        $performanceCycleInstance->transitionTo($newStatus);

        return new PerformanceCycleInstanceResource($performanceCycleInstance->load('performanceCycle'));
    }

    /**
     * Remove the specified performance cycle instance.
     */
    public function destroy(string $tenant, PerformanceCycleInstance $performanceCycleInstance): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        if (! $performanceCycleInstance->isDeletable()) {
            return response()->json([
                'message' => 'Only draft instances can be deleted.',
            ], 422);
        }

        // Delete participants first
        $performanceCycleInstance->participants()->delete();

        // Delete the instance
        $performanceCycleInstance->delete();

        return response()->json(null, 204);
    }
}
