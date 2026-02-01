<?php

namespace App\Http\Controllers\Organization;

use App\Enums\ScheduleType;
use App\Http\Controllers\Controller;
use App\Http\Resources\WorkScheduleResource;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inertia page controller for Work Schedules management.
 *
 * Handles rendering the Work Schedules index page with schedule data
 * and enum options for the frontend form components.
 */
class WorkScheduleController extends Controller
{
    /**
     * Display the work schedules index page.
     */
    public function index(): Response
    {
        Gate::authorize('can-manage-organization');

        $schedules = WorkSchedule::query()
            ->with('employeeScheduleAssignments')
            ->orderBy('name')
            ->get();

        return Inertia::render('Organization/WorkSchedules/Index', [
            'schedules' => WorkScheduleResource::collection($schedules),
            'scheduleTypes' => $this->getScheduleTypeOptions(),
        ]);
    }

    /**
     * Get schedule type options as array for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getScheduleTypeOptions(): array
    {
        return array_map(
            fn (ScheduleType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            ScheduleType::cases()
        );
    }
}
