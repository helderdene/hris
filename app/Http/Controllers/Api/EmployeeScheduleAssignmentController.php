<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeScheduleAssignmentRequest;
use App\Http\Requests\UpdateEmployeeScheduleAssignmentRequest;
use App\Http\Resources\EmployeeScheduleAssignmentResource;
use App\Models\EmployeeScheduleAssignment;
use App\Models\WorkSchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EmployeeScheduleAssignmentController extends Controller
{
    /**
     * Display a listing of assignments for a work schedule.
     */
    public function index(WorkSchedule $workSchedule): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $assignments = $workSchedule->employeeScheduleAssignments()
            ->with(['employee', 'workSchedule'])
            ->orderBy('effective_date', 'desc')
            ->get();

        return EmployeeScheduleAssignmentResource::collection($assignments);
    }

    /**
     * Store a newly created assignment.
     */
    public function store(StoreEmployeeScheduleAssignmentRequest $request, WorkSchedule $workSchedule): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $assignment = new EmployeeScheduleAssignment($request->validated());
        $assignment->work_schedule_id = $workSchedule->id;
        $assignment->save();

        $assignment->load(['employee', 'workSchedule']);

        return (new EmployeeScheduleAssignmentResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified assignment.
     */
    public function update(
        UpdateEmployeeScheduleAssignmentRequest $request,
        WorkSchedule $workSchedule,
        EmployeeScheduleAssignment $assignment
    ): EmployeeScheduleAssignmentResource {
        Gate::authorize('can-manage-organization');

        $assignment->update($request->validated());

        $assignment->load(['employee', 'workSchedule']);

        return new EmployeeScheduleAssignmentResource($assignment);
    }

    /**
     * Remove the specified assignment.
     */
    public function destroy(WorkSchedule $workSchedule, EmployeeScheduleAssignment $assignment): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $assignment->delete();

        return response()->json([
            'message' => 'Schedule assignment removed successfully.',
        ]);
    }
}
