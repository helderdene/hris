<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLeaveTypeRequest;
use App\Http\Requests\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Database\Seeders\PhilippineStatutoryLeaveSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class LeaveTypeController extends Controller
{
    /**
     * Display a listing of leave types.
     *
     * Supports filtering by category and active status.
     * All authenticated users can view leave types (read-only access).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = LeaveType::query()
            ->orderBy('leave_category')
            ->orderBy('name');

        // Filter by category
        if ($request->filled('category')) {
            $query->byCategory($request->input('category'));
        }

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Filter by statutory
        if ($request->has('statutory')) {
            $query->where('is_statutory', $request->boolean('statutory'));
        }

        return LeaveTypeResource::collection($query->get());
    }

    /**
     * Store a newly created leave type.
     *
     * Only users with organization management access can create leave types.
     */
    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $leaveType = LeaveType::create($request->validated());

        return (new LeaveTypeResource($leaveType))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified leave type.
     */
    public function show(LeaveType $leaveType): LeaveTypeResource
    {
        return new LeaveTypeResource($leaveType);
    }

    /**
     * Update the specified leave type.
     *
     * Only users with organization management access can update leave types.
     */
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): LeaveTypeResource
    {
        Gate::authorize('can-manage-organization');

        $leaveType->update($request->validated());

        return new LeaveTypeResource($leaveType);
    }

    /**
     * Remove the specified leave type (soft delete).
     *
     * Only users with organization management access can delete leave types.
     * Blocks deletion when any employee has consumed (used > 0) or pending
     * days for this type, or when any leave application references it.
     * Untouched zero-day balances are cleaned up in the same transaction so
     * the deletion does not leave dangling rows.
     */
    public function destroy(LeaveType $leaveType): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $activeBalances = LeaveBalance::query()
            ->where('leave_type_id', $leaveType->id)
            ->where(function ($q) {
                $q->where('used', '>', 0)->orWhere('pending', '>', 0);
            })
            ->count();

        $applicationCount = LeaveApplication::query()
            ->where('leave_type_id', $leaveType->id)
            ->count();

        if ($activeBalances > 0 || $applicationCount > 0) {
            return response()->json([
                'message' => 'Cannot delete this leave type because it is in use.',
                'errors' => [
                    'leave_type' => array_values(array_filter([
                        $activeBalances > 0 ? "{$activeBalances} employee balance(s) have used or pending days for this leave type." : null,
                        $applicationCount > 0 ? "{$applicationCount} leave application(s) reference this leave type." : null,
                    ])),
                ],
            ], 422);
        }

        DB::transaction(function () use ($leaveType): void {
            LeaveBalance::query()
                ->where('leave_type_id', $leaveType->id)
                ->delete();

            $leaveType->delete();
        });

        return response()->json([
            'message' => 'Leave type deleted successfully.',
        ]);
    }

    /**
     * Seed Philippine statutory leave types.
     *
     * Creates or updates the standard Philippine statutory leaves.
     * Only users with organization management access can seed leave types.
     */
    public function seedStatutory(): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $seeder = new PhilippineStatutoryLeaveSeeder;
        $seeder->run();

        $count = LeaveType::statutory()->count();

        return response()->json([
            'message' => "Successfully seeded {$count} statutory leave types.",
            'count' => $count,
            'leave_types' => LeaveTypeResource::collection(LeaveType::statutory()->get()),
        ]);
    }
}
