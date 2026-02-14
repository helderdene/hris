<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkAssignKpiRequest;
use App\Http\Requests\RecordKpiProgressRequest;
use App\Http\Requests\StoreKpiAssignmentRequest;
use App\Http\Requests\UpdateKpiAssignmentRequest;
use App\Http\Resources\KpiAssignmentResource;
use App\Http\Resources\KpiProgressEntryResource;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Services\KpiAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class KpiAssignmentController extends Controller
{
    public function __construct(
        protected KpiAssignmentService $kpiAssignmentService
    ) {}

    /**
     * Display a listing of KPI assignments.
     *
     * Supports filtering by participant_id, instance_id, and status.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = KpiAssignment::query()
            ->with([
                'kpiTemplate',
                'performanceCycleParticipant.employee',
                'performanceCycleParticipant.performanceCycleInstance',
            ])
            ->orderBy('created_at', 'desc');

        // Filter by participant
        if ($request->filled('participant_id')) {
            $query->where('performance_cycle_participant_id', $request->input('participant_id'));
        }

        // Filter by performance cycle instance
        if ($request->filled('instance_id')) {
            $query->whereHas('performanceCycleParticipant', function ($q) use ($request) {
                $q->where('performance_cycle_instance_id', $request->input('instance_id'));
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return KpiAssignmentResource::collection($query->get());
    }

    /**
     * Store a newly created KPI assignment.
     */
    public function store(StoreKpiAssignmentRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $assignment = KpiAssignment::create([
            'kpi_template_id' => $data['kpi_template_id'],
            'performance_cycle_participant_id' => $data['performance_cycle_participant_id'],
            'target_value' => $data['target_value'],
            'weight' => $data['weight'] ?? 1.0,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        $assignment->load([
            'kpiTemplate',
            'performanceCycleParticipant.employee',
        ]);

        return (new KpiAssignmentResource($assignment))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Bulk assign a KPI template to multiple participants.
     */
    public function bulkAssign(BulkAssignKpiRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $template = KpiTemplate::findOrFail($data['kpi_template_id']);

        $assignments = $this->kpiAssignmentService->bulkAssignKpi(
            $template,
            $data['participant_ids'],
            $data['target_value'],
            $data['weight'] ?? 1.0
        );

        $assignments->load([
            'kpiTemplate',
            'performanceCycleParticipant.employee',
        ]);

        return response()->json([
            'message' => count($assignments).' KPI assignments created successfully.',
            'data' => KpiAssignmentResource::collection($assignments),
            'skipped' => count($data['participant_ids']) - count($assignments),
        ], 201);
    }

    /**
     * Display the specified KPI assignment.
     */
    public function show(KpiAssignment $kpiAssignment): KpiAssignmentResource
    {
        Gate::authorize('can-manage-organization');

        $kpiAssignment->load([
            'kpiTemplate',
            'performanceCycleParticipant.employee',
            'progressEntries.recordedByUser',
        ]);

        return new KpiAssignmentResource($kpiAssignment);
    }

    /**
     * Update the specified KPI assignment.
     */
    public function update(
        UpdateKpiAssignmentRequest $request,
        KpiAssignment $kpiAssignment
    ): KpiAssignmentResource {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $kpiAssignment->update($data);

        // Recalculate achievement if target changed
        if (isset($data['target_value'])) {
            $this->kpiAssignmentService->calculateAchievement($kpiAssignment);
        }

        $kpiAssignment->load([
            'kpiTemplate',
            'performanceCycleParticipant.employee',
        ]);

        return new KpiAssignmentResource($kpiAssignment);
    }

    /**
     * Remove the specified KPI assignment.
     */
    public function destroy(KpiAssignment $kpiAssignment): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        // Delete associated progress entries first
        $kpiAssignment->progressEntries()->delete();

        $kpiAssignment->delete();

        return response()->json([
            'message' => 'KPI assignment deleted successfully.',
        ]);
    }

    /**
     * Record progress for a KPI assignment.
     */
    public function recordProgress(
        RecordKpiProgressRequest $request,
        KpiAssignment $kpiAssignment
    ): JsonResponse {
        Gate::authorize('can-manage-organization');

        $data = $request->validated();

        $entry = $this->kpiAssignmentService->recordProgress(
            $kpiAssignment,
            $data['value'],
            $data['notes'] ?? null,
            $request->user()
        );

        $entry->load('recordedByUser');

        $kpiAssignment->refresh();
        $kpiAssignment->load('kpiTemplate');

        return response()->json([
            'message' => 'Progress recorded successfully.',
            'progress_entry' => new KpiProgressEntryResource($entry),
            'assignment' => new KpiAssignmentResource($kpiAssignment),
        ]);
    }

    /**
     * Mark a KPI assignment as completed.
     */
    public function complete(KpiAssignment $kpiAssignment): KpiAssignmentResource
    {
        Gate::authorize('can-manage-organization');

        $this->kpiAssignmentService->markCompleted($kpiAssignment);

        $kpiAssignment->refresh();
        $kpiAssignment->load([
            'kpiTemplate',
            'performanceCycleParticipant.employee',
        ]);

        return new KpiAssignmentResource($kpiAssignment);
    }

    /**
     * Get progress history for a KPI assignment.
     */
    public function progressHistory(KpiAssignment $kpiAssignment): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $entries = $this->kpiAssignmentService->getProgressHistory($kpiAssignment);

        return KpiProgressEntryResource::collection($entries);
    }
}
