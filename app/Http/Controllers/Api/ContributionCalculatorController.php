<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CalculateContributionRequest;
use App\Services\ContributionCalculatorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ContributionCalculatorController extends Controller
{
    public function __construct(
        protected ContributionCalculatorService $calculator
    ) {}

    /**
     * Calculate all government contributions for a given salary.
     */
    public function calculate(CalculateContributionRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $salary = (float) $request->validated('salary');
        $effectiveDate = $request->validated('effective_date')
            ? Carbon::parse($request->validated('effective_date'))
            : null;

        $contributions = $this->calculator->calculateAll($salary, $effectiveDate);

        return response()->json([
            'data' => [
                'salary' => $salary,
                'effective_date' => $effectiveDate?->toDateString() ?? now()->toDateString(),
                'contributions' => $contributions,
            ],
        ]);
    }

    /**
     * Calculate SSS contribution for a given salary.
     */
    public function calculateSss(CalculateContributionRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $salary = (float) $request->validated('salary');
        $effectiveDate = $request->validated('effective_date')
            ? Carbon::parse($request->validated('effective_date'))
            : null;

        $contribution = $this->calculator->calculateSss($salary, $effectiveDate);

        return response()->json([
            'data' => [
                'salary' => $salary,
                'effective_date' => $effectiveDate?->toDateString() ?? now()->toDateString(),
                'contribution' => $contribution,
            ],
        ]);
    }

    /**
     * Calculate PhilHealth contribution for a given salary.
     */
    public function calculatePhilhealth(CalculateContributionRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $salary = (float) $request->validated('salary');
        $effectiveDate = $request->validated('effective_date')
            ? Carbon::parse($request->validated('effective_date'))
            : null;

        $contribution = $this->calculator->calculatePhilHealth($salary, $effectiveDate);

        return response()->json([
            'data' => [
                'salary' => $salary,
                'effective_date' => $effectiveDate?->toDateString() ?? now()->toDateString(),
                'contribution' => $contribution,
            ],
        ]);
    }

    /**
     * Calculate Pag-IBIG contribution for a given salary.
     */
    public function calculatePagibig(CalculateContributionRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $salary = (float) $request->validated('salary');
        $effectiveDate = $request->validated('effective_date')
            ? Carbon::parse($request->validated('effective_date'))
            : null;

        $contribution = $this->calculator->calculatePagibig($salary, $effectiveDate);

        return response()->json([
            'data' => [
                'salary' => $salary,
                'effective_date' => $effectiveDate?->toDateString() ?? now()->toDateString(),
                'contribution' => $contribution,
            ],
        ]);
    }

    /**
     * Check if all contribution tables are configured.
     */
    public function status(): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $tables = $this->calculator->getActiveTables();

        return response()->json([
            'data' => [
                'has_all_tables' => $this->calculator->hasAllTables(),
                'sss_configured' => $tables['sss'] !== null,
                'philhealth_configured' => $tables['philhealth'] !== null,
                'pagibig_configured' => $tables['pagibig'] !== null,
            ],
        ]);
    }
}
