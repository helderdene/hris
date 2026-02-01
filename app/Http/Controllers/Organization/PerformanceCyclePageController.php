<?php

namespace App\Http\Controllers\Organization;

use App\Enums\PerformanceCycleInstanceStatus;
use App\Enums\PerformanceCycleType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PerformanceCycleInstanceResource;
use App\Http\Resources\PerformanceCycleResource;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PerformanceCyclePageController extends Controller
{
    /**
     * Display the performance cycles management page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        // Get all performance cycles with instance counts
        $cycles = PerformanceCycle::query()
            ->withCount('performanceCycleInstances')
            ->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->get();

        // Get instances for the selected year (default to current year)
        $year = $request->input('year', now()->year);
        $cycleId = $request->input('cycle_id');

        $instancesQuery = PerformanceCycleInstance::query()
            ->with(['performanceCycle'])
            ->forYear((int) $year)
            ->orderBy('instance_number');

        if ($cycleId) {
            $instancesQuery->forCycle((int) $cycleId);
        }

        $instances = $instancesQuery->get();

        // Get available years (past 2 years to future 2 years)
        $currentYear = now()->year;
        $availableYears = range($currentYear - 2, $currentYear + 2);

        return Inertia::render('Organization/PerformanceCycles/Index', [
            'cycles' => PerformanceCycleResource::collection($cycles),
            'instances' => PerformanceCycleInstanceResource::collection($instances),
            'cycleTypes' => $this->getCycleTypeOptions(),
            'instanceStatuses' => $this->getInstanceStatusOptions(),
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
     * @return array<int, array{value: string, label: string, description: string, is_recurring: bool, instances_per_year: int|null}>
     */
    private function getCycleTypeOptions(): array
    {
        return array_map(
            fn (PerformanceCycleType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'is_recurring' => $type->isRecurring(),
                'instances_per_year' => $type->instancesPerYear(),
            ],
            PerformanceCycleType::cases()
        );
    }

    /**
     * Get instance status options as array for frontend.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getInstanceStatusOptions(): array
    {
        return array_map(
            fn (PerformanceCycleInstanceStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->colorClass(),
            ],
            PerformanceCycleInstanceStatus::cases()
        );
    }
}
