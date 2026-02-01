<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ComputePayrollRequest;
use App\Jobs\ProcessPayrollPeriodJob;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollComputationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PayrollComputationController extends Controller
{
    public function __construct(
        protected PayrollComputationService $computationService
    ) {}

    /**
     * Trigger payroll computation for a period.
     *
     * Dispatches a background job to process all or selected employees.
     */
    public function compute(
        ComputePayrollRequest $request,
        string $tenant,
        PayrollPeriod $payrollPeriod
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        if (! $payrollPeriod->isEditable()) {
            return response()->json([
                'message' => "Cannot compute payroll for a period with status '{$payrollPeriod->status->label()}'.",
            ], 422);
        }

        $tenantModel = tenant();

        ProcessPayrollPeriodJob::dispatch(
            $payrollPeriod->id,
            $tenantModel->id,
            $request->input('employee_ids'),
            $request->boolean('force_recompute', false)
        );

        return response()->json([
            'message' => 'Payroll computation job has been dispatched.',
            'period_id' => $payrollPeriod->id,
            'employee_ids' => $request->input('employee_ids'),
            'force_recompute' => $request->boolean('force_recompute', false),
        ]);
    }

    /**
     * Preview payroll computation for an employee without saving.
     */
    public function preview(
        string $tenant,
        PayrollPeriod $payrollPeriod,
        Employee $employee
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        $preview = $this->computationService->preview($payrollPeriod, $employee);

        if (isset($preview['error'])) {
            return response()->json([
                'message' => $preview['error'],
            ], 422);
        }

        return response()->json($preview);
    }

    /**
     * Recompute payroll for specific employees in a period.
     */
    public function recompute(
        ComputePayrollRequest $request,
        string $tenant,
        PayrollPeriod $payrollPeriod
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        if (! $payrollPeriod->isEditable()) {
            return response()->json([
                'message' => "Cannot recompute payroll for a period with status '{$payrollPeriod->status->label()}'.",
            ], 422);
        }

        $employeeIds = $request->input('employee_ids');

        if (empty($employeeIds)) {
            return response()->json([
                'message' => 'Employee IDs are required for recomputation.',
            ], 422);
        }

        $tenantModel = tenant();

        ProcessPayrollPeriodJob::dispatch(
            $payrollPeriod->id,
            $tenantModel->id,
            $employeeIds,
            true
        );

        return response()->json([
            'message' => 'Payroll recomputation job has been dispatched.',
            'period_id' => $payrollPeriod->id,
            'employee_ids' => $employeeIds,
        ]);
    }
}
