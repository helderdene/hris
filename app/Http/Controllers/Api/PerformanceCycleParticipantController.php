<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignPerformanceCycleParticipantsRequest;
use App\Http\Resources\PerformanceCycleParticipantResource;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Services\PerformanceCycleInstanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PerformanceCycleParticipantController extends Controller
{
    public function __construct(
        protected PerformanceCycleInstanceService $instanceService
    ) {}

    /**
     * Display a listing of participants for an instance.
     */
    public function index(
        Request $request,
        PerformanceCycleInstance $performanceCycleInstance
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-organization');

        $query = $performanceCycleInstance->participants()
            ->with(['employee.position', 'employee.department', 'manager'])
            ->orderBy('is_excluded')
            ->orderBy('created_at');

        // Filter by excluded status
        if ($request->filled('excluded')) {
            $excluded = filter_var($request->input('excluded'), FILTER_VALIDATE_BOOLEAN);
            if ($excluded) {
                $query->excluded();
            } else {
                $query->included();
            }
        }

        // Filter by status
        if ($request->filled('status')) {
            if ($request->input('status') === 'pending') {
                $query->pending();
            } elseif ($request->input('status') === 'completed') {
                $query->completed();
            }
        }

        return PerformanceCycleParticipantResource::collection($query->get());
    }

    /**
     * Assign participants to an instance.
     */
    public function assign(
        AssignPerformanceCycleParticipantsRequest $request,
        PerformanceCycleInstance $performanceCycleInstance
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        if (! $performanceCycleInstance->isEditable()) {
            return response()->json([
                'message' => 'Cannot assign participants to this instance in its current status.',
            ], 422);
        }

        $excludedIds = $request->validated('excluded_employee_ids', []);

        $participants = $this->instanceService->assignParticipants(
            $performanceCycleInstance,
            $excludedIds
        );

        $includedCount = $participants->where('is_excluded', false)->count();
        $excludedCount = $participants->where('is_excluded', true)->count();

        // Eager load relationships for each participant
        $participants->each(function ($participant) {
            $participant->load(['employee.position', 'employee.department', 'manager']);
        });

        return response()->json([
            'message' => "Assigned {$includedCount} participant(s) with {$excludedCount} exclusion(s).",
            'assigned_count' => $includedCount,
            'excluded_count' => $excludedCount,
            'participants' => PerformanceCycleParticipantResource::collection($participants),
        ]);
    }

    /**
     * Update a participant.
     */
    public function update(
        Request $request,
        PerformanceCycleInstance $performanceCycleInstance,
        PerformanceCycleParticipant $participant
    ): PerformanceCycleParticipantResource {
        Gate::authorize('can-manage-organization');

        // Validate that the participant belongs to this instance
        if ($participant->performance_cycle_instance_id !== $performanceCycleInstance->id) {
            abort(404);
        }

        $validated = $request->validate([
            'manager_id' => ['nullable', 'integer', 'exists:employees,id'],
            'is_excluded' => ['boolean'],
            'status' => ['nullable', 'string', 'in:pending,completed'],
        ]);

        // Handle completed_at timestamp
        if (isset($validated['status']) && $validated['status'] === 'completed') {
            $validated['completed_at'] = now();
        }

        $participant->update($validated);

        // Update employee count if exclusion status changed
        if (isset($validated['is_excluded'])) {
            $performanceCycleInstance->updateEmployeeCount();
        }

        return new PerformanceCycleParticipantResource(
            $participant->load(['employee.position', 'employee.department', 'manager'])
        );
    }

    /**
     * Remove a participant.
     */
    public function destroy(
        PerformanceCycleInstance $performanceCycleInstance,
        PerformanceCycleParticipant $participant
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        // Validate that the participant belongs to this instance
        if ($participant->performance_cycle_instance_id !== $performanceCycleInstance->id) {
            abort(404);
        }

        if (! $performanceCycleInstance->isEditable()) {
            return response()->json([
                'message' => 'Cannot remove participants from this instance in its current status.',
            ], 422);
        }

        $participant->delete();

        // Update employee count
        $performanceCycleInstance->updateEmployeeCount();

        return response()->json(null, 204);
    }
}
