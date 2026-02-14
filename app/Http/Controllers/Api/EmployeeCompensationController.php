<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeCompensationRequest;
use App\Http\Resources\CompensationHistoryResource;
use App\Http\Resources\CompensationResource;
use App\Models\CompensationHistory;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmployeeCompensationController extends Controller
{
    /**
     * Display the compensation and history for an employee.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function index(Employee $employee): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        $compensation = $employee->compensation;
        $history = $employee->compensationHistory()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'data' => [
                'compensation' => $compensation ? new CompensationResource($compensation) : null,
                'history' => CompensationHistoryResource::collection($history),
            ],
        ]);
    }

    /**
     * Store or update compensation for an employee.
     *
     * Creates a new compensation record if none exists, or updates the existing one.
     * In both cases, creates a history record to track the change.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function store(StoreEmployeeCompensationRequest $request, Employee $employee): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        $validated = $request->validated();

        $result = DB::transaction(function () use ($employee, $validated) {
            $existingCompensation = $employee->compensation;

            // Capture previous values before any update
            $previousBasicPay = $existingCompensation?->basic_pay;
            $previousPayType = $existingCompensation?->pay_type;

            // End the current history record if one exists
            if ($existingCompensation) {
                CompensationHistory::where('employee_id', $employee->id)
                    ->whereNull('ended_at')
                    ->update(['ended_at' => now()]);
            }

            // Create or update the compensation record
            $compensationData = [
                'basic_pay' => $validated['basic_pay'],
                'pay_type' => $validated['pay_type'],
                'effective_date' => $validated['effective_date'],
                'bank_name' => $validated['bank_name'] ?? null,
                'account_name' => $validated['account_name'] ?? null,
                'account_number' => $validated['account_number'] ?? null,
                'account_type' => $validated['account_type'] ?? null,
            ];

            if ($existingCompensation) {
                $existingCompensation->update($compensationData);
                $compensation = $existingCompensation->fresh();
            } else {
                $compensation = EmployeeCompensation::create([
                    'employee_id' => $employee->id,
                    ...$compensationData,
                ]);
            }

            // Create a new history record
            $historyEntry = CompensationHistory::create([
                'employee_id' => $employee->id,
                'previous_basic_pay' => $previousBasicPay,
                'new_basic_pay' => $validated['basic_pay'],
                'previous_pay_type' => $previousPayType,
                'new_pay_type' => $validated['pay_type'],
                'effective_date' => $validated['effective_date'],
                'changed_by' => auth()->id(),
                'remarks' => $validated['remarks'] ?? null,
                'ended_at' => null,
            ]);

            return [
                'compensation' => $compensation,
                'history_entry' => $historyEntry,
            ];
        });

        return response()->json([
            'data' => [
                'compensation' => new CompensationResource($result['compensation']),
                'history_entry' => new CompensationHistoryResource($result['history_entry']),
            ],
        ], 201);
    }
}
