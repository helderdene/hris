<?php

namespace App\Http\Controllers\Organization;

use App\Enums\PayrollCycleType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollPeriodType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollCycleResource;
use App\Http\Resources\PayrollPeriodResource;
use App\Models\PayrollCycle;
use App\Models\PayrollPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PayrollPeriodPageController extends Controller
{
    /**
     * Display the payroll periods management page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        // Get all payroll cycles with period counts
        $cycles = PayrollCycle::query()
            ->withCount('payrollPeriods')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // Get periods for the selected year (default to current year)
        $year = $request->input('year', now()->year);
        $cycleId = $request->input('cycle_id');

        $periodsQuery = PayrollPeriod::query()
            ->with(['payrollCycle'])
            ->forYear((int) $year)
            ->orderBy('period_number');

        if ($cycleId) {
            $periodsQuery->forCycle((int) $cycleId);
        }

        $periods = $periodsQuery->get();

        // Get available years (past 2 years to future 2 years)
        $currentYear = now()->year;
        $availableYears = range($currentYear - 2, $currentYear + 2);

        return Inertia::render('Organization/PayrollPeriods/Index', [
            'cycles' => PayrollCycleResource::collection($cycles),
            'periods' => PayrollPeriodResource::collection($periods),
            'cycleTypes' => $this->getCycleTypeOptions(),
            'periodTypes' => $this->getPeriodTypeOptions(),
            'periodStatuses' => $this->getPeriodStatusOptions(),
            'availableYears' => $availableYears,
            'filters' => [
                'year' => (int) $year,
                'cycle_id' => $cycleId ? (int) $cycleId : null,
            ],
        ]);
    }

    /**
     * Get cycle type options as array for frontend.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function getCycleTypeOptions(): array
    {
        return array_map(
            fn (PayrollCycleType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'is_recurring' => $type->isRecurring(),
                'periods_per_year' => $type->periodsPerYear(),
            ],
            PayrollCycleType::cases()
        );
    }

    /**
     * Get period type options as array for frontend.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function getPeriodTypeOptions(): array
    {
        return array_map(
            fn (PayrollPeriodType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            PayrollPeriodType::cases()
        );
    }

    /**
     * Get period status options as array for frontend.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getPeriodStatusOptions(): array
    {
        return array_map(
            fn (PayrollPeriodStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->colorClass(),
            ],
            PayrollPeriodStatus::cases()
        );
    }
}
