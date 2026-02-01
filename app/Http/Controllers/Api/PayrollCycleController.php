<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePayrollCycleRequest;
use App\Http\Requests\UpdatePayrollCycleRequest;
use App\Http\Resources\PayrollCycleResource;
use App\Models\PayrollCycle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PayrollCycleController extends Controller
{
    /**
     * Display a listing of payroll cycles.
     *
     * Supports filtering by status and cycle_type.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = PayrollCycle::query()
            ->withCount('payrollPeriods')
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

        return PayrollCycleResource::collection($query->get());
    }

    /**
     * Store a newly created payroll cycle.
     */
    public function store(StorePayrollCycleRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        // Set default cutoff rules if not provided
        if (empty($data['cutoff_rules']) && isset($data['cycle_type'])) {
            $cycleType = \App\Enums\PayrollCycleType::from($data['cycle_type']);
            $data['cutoff_rules'] = PayrollCycle::getDefaultCutoffRules($cycleType);
        }

        $cycle = PayrollCycle::create($data);

        // If this is set as default, update others
        if ($cycle->is_default) {
            $cycle->setAsDefault();
        }

        return (new PayrollCycleResource($cycle))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified payroll cycle.
     */
    public function show(string $tenant, PayrollCycle $payrollCycle): PayrollCycleResource
    {
        Gate::authorize('can-manage-organization');

        $payrollCycle->loadCount('payrollPeriods');

        return new PayrollCycleResource($payrollCycle);
    }

    /**
     * Update the specified payroll cycle.
     */
    public function update(
        UpdatePayrollCycleRequest $request,
        string $tenant,
        PayrollCycle $payrollCycle
    ): PayrollCycleResource {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $payrollCycle->update($data);

        // If this is set as default, update others
        if (isset($data['is_default']) && $data['is_default']) {
            $payrollCycle->setAsDefault();
        }

        $payrollCycle->loadCount('payrollPeriods');

        return new PayrollCycleResource($payrollCycle);
    }

    /**
     * Remove the specified payroll cycle.
     *
     * Cannot delete if there are associated periods that are not in draft status.
     */
    public function destroy(string $tenant, PayrollCycle $payrollCycle): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Check if there are non-draft periods
        $nonDraftPeriods = $payrollCycle->payrollPeriods()
            ->where('status', '!=', 'draft')
            ->count();

        if ($nonDraftPeriods > 0) {
            return response()->json([
                'message' => 'Cannot delete this cycle because it has periods that are not in draft status.',
            ], 422);
        }

        // Delete all draft periods first
        $payrollCycle->payrollPeriods()->delete();

        // Delete the cycle
        $payrollCycle->delete();

        return response()->json([
            'message' => 'Payroll cycle deleted successfully.',
        ]);
    }
}
