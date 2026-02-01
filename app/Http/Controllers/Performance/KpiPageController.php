<?php

namespace App\Http\Controllers\Performance;

use App\Enums\KpiAssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\KpiAssignmentResource;
use App\Http\Resources\KpiTemplateResource;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class KpiPageController extends Controller
{
    /**
     * Display the KPI management page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-organization');

        // Get all KPI templates with assignment counts
        $templates = KpiTemplate::query()
            ->withCount('kpiAssignments')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        // Get KPI assignments with filters
        $assignmentsQuery = KpiAssignment::query()
            ->with([
                'kpiTemplate',
                'performanceCycleParticipant.employee',
                'performanceCycleParticipant.performanceCycleInstance.performanceCycle',
            ])
            ->orderBy('created_at', 'desc');

        // Filter by performance cycle instance
        if ($request->filled('instance_id')) {
            $assignmentsQuery->whereHas('performanceCycleParticipant', function ($q) use ($request) {
                $q->where('performance_cycle_instance_id', $request->input('instance_id'));
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $assignmentsQuery->where('status', $request->input('status'));
        }

        // Filter by participant
        if ($request->filled('participant_id')) {
            $assignmentsQuery->where('performance_cycle_participant_id', $request->input('participant_id'));
        }

        $assignments = $assignmentsQuery->get();

        // Get available instances for filtering
        $instances = PerformanceCycleInstance::query()
            ->with('performanceCycle')
            ->whereIn('status', ['active', 'in_evaluation'])
            ->orderBy('year', 'desc')
            ->orderBy('instance_number')
            ->get()
            ->map(fn ($instance) => [
                'id' => $instance->id,
                'name' => $instance->name,
                'cycle_name' => $instance->performanceCycle?->name,
                'year' => $instance->year,
            ]);

        // Get participants for the selected instance (for assignment dropdowns)
        $participants = [];
        if ($request->filled('instance_id')) {
            $participants = PerformanceCycleParticipant::query()
                ->with('employee')
                ->where('performance_cycle_instance_id', $request->input('instance_id'))
                ->where('is_excluded', false)
                ->get()
                ->map(fn ($participant) => [
                    'id' => $participant->id,
                    'employee_id' => $participant->employee_id,
                    'employee_name' => $participant->employee?->full_name ?? 'Unknown',
                    'employee_code' => $participant->employee?->employee_code,
                ]);
        }

        // Get unique categories from templates
        $categories = KpiTemplate::query()
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values()
            ->toArray();

        return Inertia::render('Performance/Kpis/Index', [
            'templates' => KpiTemplateResource::collection($templates),
            'assignments' => KpiAssignmentResource::collection($assignments),
            'instances' => $instances,
            'participants' => $participants,
            'kpiStatuses' => $this->getKpiStatusOptions(),
            'categories' => $categories,
            'filters' => [
                'instance_id' => $request->input('instance_id') ? (int) $request->input('instance_id') : null,
                'status' => $request->input('status'),
                'participant_id' => $request->input('participant_id') ? (int) $request->input('participant_id') : null,
            ],
        ]);
    }

    /**
     * Get KPI status options as array for frontend.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getKpiStatusOptions(): array
    {
        return array_map(
            fn (KpiAssignmentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->colorClass(),
            ],
            KpiAssignmentStatus::cases()
        );
    }
}
