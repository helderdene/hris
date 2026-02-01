<?php

namespace App\Http\Controllers\Api;

use App\Enums\AdjustmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeAdjustmentRequest;
use App\Http\Requests\UpdateAdjustmentStatusRequest;
use App\Http\Requests\UpdateEmployeeAdjustmentRequest;
use App\Http\Resources\EmployeeAdjustmentListResource;
use App\Http\Resources\EmployeeAdjustmentResource;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\PayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EmployeeAdjustmentController extends Controller
{
    /**
     * Display a listing of employee adjustments.
     *
     * Supports filtering by status, category, type, frequency, and employee_id.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = EmployeeAdjustment::query()
            ->with(['employee.department', 'employee.position'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('adjustment_category', $request->input('category'));
        }

        if ($request->filled('adjustment_type')) {
            $query->where('adjustment_type', $request->input('adjustment_type'));
        }

        if ($request->filled('frequency')) {
            $query->where('frequency', $request->input('frequency'));
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $perPage = $request->input('per_page', 25);

        if ($request->boolean('paginate', true)) {
            return EmployeeAdjustmentListResource::collection($query->paginate($perPage));
        }

        return EmployeeAdjustmentListResource::collection($query->get());
    }

    /**
     * Store a newly created employee adjustment.
     */
    public function store(StoreEmployeeAdjustmentRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $adjustment = EmployeeAdjustment::create($request->validatedWithDefaults());

        $adjustment->load(['employee.department', 'employee.position']);

        return (new EmployeeAdjustmentResource($adjustment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified employee adjustment.
     */
    public function show(string $tenant, EmployeeAdjustment $adjustment): EmployeeAdjustmentResource
    {
        Gate::authorize('can-manage-organization');

        $adjustment->load([
            'employee.department',
            'employee.position',
            'targetPayrollPeriod',
            'applications.payrollPeriod',
        ]);

        return new EmployeeAdjustmentResource($adjustment);
    }

    /**
     * Update the specified employee adjustment.
     */
    public function update(
        UpdateEmployeeAdjustmentRequest $request,
        string $tenant,
        EmployeeAdjustment $adjustment
    ): EmployeeAdjustmentResource {
        Gate::authorize('can-manage-organization');

        if (! in_array($adjustment->status, [AdjustmentStatus::Active, AdjustmentStatus::OnHold])) {
            abort(422, 'Only active or on-hold adjustments can be updated.');
        }

        $adjustment->update($request->validated());

        $adjustment->load(['employee.department', 'employee.position']);

        return new EmployeeAdjustmentResource($adjustment);
    }

    /**
     * Remove the specified employee adjustment.
     */
    public function destroy(string $tenant, EmployeeAdjustment $adjustment): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        if ($adjustment->applications()->exists()) {
            return response()->json([
                'message' => 'Cannot delete an adjustment that has been applied to payroll.',
            ], 422);
        }

        $adjustment->delete();

        return response()->json([
            'message' => 'Adjustment deleted successfully.',
        ]);
    }

    /**
     * Update the status of an employee adjustment.
     */
    public function updateStatus(
        UpdateAdjustmentStatusRequest $request,
        string $tenant,
        EmployeeAdjustment $adjustment
    ): EmployeeAdjustmentResource {
        Gate::authorize('can-manage-organization');

        $newStatus = AdjustmentStatus::from($request->validated('status'));
        $notes = $request->validated('notes');

        if (! $adjustment->status->canTransitionTo($newStatus)) {
            abort(422, "Cannot transition from {$adjustment->status->label()} to {$newStatus->label()}.");
        }

        match ($newStatus) {
            AdjustmentStatus::OnHold => $adjustment->putOnHold($notes),
            AdjustmentStatus::Active => $adjustment->resume(),
            AdjustmentStatus::Completed => $adjustment->markAsCompleted(),
            AdjustmentStatus::Cancelled => $adjustment->cancel($notes),
        };

        $adjustment->load(['employee.department', 'employee.position']);

        return new EmployeeAdjustmentResource($adjustment);
    }

    /**
     * Get adjustments for a specific employee.
     */
    public function employeeAdjustments(
        Request $request,
        string $tenant,
        Employee $employee
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-organization');

        $query = $employee->adjustments()
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('category')) {
            $query->where('adjustment_category', $request->input('category'));
        }

        if ($request->boolean('active_only')) {
            $query->active();
        }

        return EmployeeAdjustmentResource::collection($query->get());
    }

    /**
     * Get adjustments applicable to a specific payroll period.
     */
    public function periodAdjustments(
        Request $request,
        string $tenant,
        PayrollPeriod $payrollPeriod
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-organization');

        $query = EmployeeAdjustment::query()
            ->with(['employee.department', 'employee.position'])
            ->applicableForPeriod($payrollPeriod)
            ->orderBy('employee_id')
            ->orderBy('adjustment_category');

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('category')) {
            $query->where('adjustment_category', $request->input('category'));
        }

        return EmployeeAdjustmentListResource::collection($query->get());
    }
}
