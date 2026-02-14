<?php

namespace App\Http\Controllers\Api;

use App\Enums\ScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkScheduleRequest;
use App\Http\Requests\UpdateWorkScheduleRequest;
use App\Http\Resources\WorkScheduleResource;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkScheduleController extends Controller
{
    /**
     * Display a listing of work schedules with optional filtering.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = WorkSchedule::query()
            ->with('employeeScheduleAssignments');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by schedule_type
        if ($request->filled('schedule_type')) {
            $scheduleType = ScheduleType::tryFrom($request->input('schedule_type'));
            if ($scheduleType) {
                $query->where('schedule_type', $scheduleType);
            }
        }

        $workSchedules = $query->orderBy('name')->get();

        return WorkScheduleResource::collection($workSchedules);
    }

    /**
     * Store a newly created work schedule.
     */
    public function store(StoreWorkScheduleRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $workSchedule = WorkSchedule::create($request->validated());

        $workSchedule->load('employeeScheduleAssignments');

        return (new WorkScheduleResource($workSchedule))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified work schedule.
     */
    public function show(WorkSchedule $workSchedule): WorkScheduleResource
    {
        Gate::authorize('can-manage-organization');

        $workSchedule->load('employeeScheduleAssignments');

        return new WorkScheduleResource($workSchedule);
    }

    /**
     * Update the specified work schedule.
     */
    public function update(UpdateWorkScheduleRequest $request, WorkSchedule $workSchedule): WorkScheduleResource
    {
        Gate::authorize('can-manage-organization');

        $workSchedule->update($request->validated());

        $workSchedule->load('employeeScheduleAssignments');

        return new WorkScheduleResource($workSchedule);
    }

    /**
     * Remove the specified work schedule.
     */
    public function destroy(WorkSchedule $workSchedule): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Check if there are any active employee assignments
        $activeAssignmentsCount = $workSchedule->employeeScheduleAssignments()
            ->active()
            ->count();

        if ($activeAssignmentsCount > 0) {
            return response()->json([
                'message' => 'Cannot delete schedule with active employee assignments.',
            ], 422);
        }

        $workSchedule->delete();

        return response()->json([
            'message' => 'Work schedule deleted successfully.',
        ]);
    }
}
