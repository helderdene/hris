<?php

namespace App\Http\Controllers\Api;

use App\Enums\PayrollPeriodStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GeneratePayrollPeriodsRequest;
use App\Http\Requests\StorePayrollPeriodRequest;
use App\Http\Requests\UpdatePayrollPeriodRequest;
use App\Http\Requests\UpdatePayrollPeriodStatusRequest;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollCycle;
use App\Models\PayrollPeriod;
use App\Services\PayrollPeriodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class PayrollPeriodController extends Controller
{
    public function __construct(
        protected PayrollPeriodService $periodService
    ) {}

    /**
     * Display a listing of payroll periods.
     *
     * Supports filtering by year, status, cycle_id, and period_type.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = PayrollPeriod::query()
            ->with(['payrollCycle', 'closedByUser'])
            ->orderBy('year', 'desc')
            ->orderBy('period_number', 'desc');

        // Filter by year (default to current year)
        if ($request->filled('year')) {
            $query->forYear((int) $request->input('year'));
        }

        // Filter by status
        if ($request->filled('status')) {
            $status = PayrollPeriodStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->byStatus($status);
            }
        }

        // Filter by cycle
        if ($request->filled('payroll_cycle_id')) {
            $query->forCycle((int) $request->input('payroll_cycle_id'));
        }

        // Filter by period type
        if ($request->filled('period_type')) {
            $periodType = \App\Enums\PayrollPeriodType::tryFrom($request->input('period_type'));
            if ($periodType) {
                $query->ofType($periodType);
            }
        }

        return PayrollPeriodResource::collection($query->get());
    }

    /**
     * Store a newly created payroll period (manual creation).
     */
    public function store(StorePayrollPeriodRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $period = PayrollPeriod::create($request->validated());
        $period->load(['payrollCycle']);

        return (new PayrollPeriodResource($period))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified payroll period.
     */
    public function show(string $tenant, PayrollPeriod $payrollPeriod): PayrollPeriodResource
    {
        Gate::authorize('can-manage-organization');

        $payrollPeriod->load(['payrollCycle', 'closedByUser']);

        return new PayrollPeriodResource($payrollPeriod);
    }

    /**
     * Update the specified payroll period.
     *
     * Only periods in draft or open status can be updated.
     */
    public function update(
        UpdatePayrollPeriodRequest $request,
        string $tenant,
        PayrollPeriod $payrollPeriod
    ): PayrollPeriodResource|JsonResponse {
        Gate::authorize('can-manage-organization');

        if (! $payrollPeriod->isEditable()) {
            return response()->json([
                'message' => "Cannot update a period with status '{$payrollPeriod->status->label()}'.",
            ], 422);
        }

        $payrollPeriod->update($request->validated());
        $payrollPeriod->load(['payrollCycle', 'closedByUser']);

        return new PayrollPeriodResource($payrollPeriod);
    }

    /**
     * Remove the specified payroll period.
     *
     * Only periods in draft status can be deleted.
     */
    public function destroy(string $tenant, PayrollPeriod $payrollPeriod): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        if (! $payrollPeriod->isDeletable()) {
            return response()->json([
                'message' => "Cannot delete a period with status '{$payrollPeriod->status->label()}'.",
            ], 422);
        }

        $payrollPeriod->delete();

        return response()->json([
            'message' => 'Payroll period deleted successfully.',
        ]);
    }

    /**
     * Generate payroll periods for a cycle and year.
     */
    public function generate(GeneratePayrollPeriodsRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();
        $cycle = PayrollCycle::findOrFail($data['payroll_cycle_id']);

        try {
            $periods = $this->periodService->generatePeriodsForYear(
                $cycle,
                $data['year'],
                $data['overwrite_existing'] ?? false
            );

            return response()->json([
                'message' => "Successfully generated {$periods->count()} payroll periods.",
                'generated_count' => $periods->count(),
                'year' => $data['year'],
                'periods' => PayrollPeriodResource::collection($periods),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Update the status of a payroll period.
     *
     * Enforces the state machine transitions.
     */
    public function updateStatus(
        UpdatePayrollPeriodStatusRequest $request,
        string $tenant,
        PayrollPeriod $payrollPeriod
    ): PayrollPeriodResource|JsonResponse {
        Gate::authorize('can-manage-organization');

        $newStatus = PayrollPeriodStatus::from($request->validated('status'));

        try {
            $payrollPeriod->transitionTo($newStatus);
            $payrollPeriod->load(['payrollCycle', 'closedByUser']);

            return new PayrollPeriodResource($payrollPeriod);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get summary statistics for a cycle's periods in a year.
     */
    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $request->validate([
            'payroll_cycle_id' => ['required', 'exists:payroll_cycles,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $cycle = PayrollCycle::findOrFail($request->input('payroll_cycle_id'));
        $summary = $this->periodService->getYearSummary($cycle, $request->input('year'));

        return response()->json($summary);
    }
}
