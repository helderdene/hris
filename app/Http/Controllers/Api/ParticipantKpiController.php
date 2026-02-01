<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\KpiAssignmentResource;
use App\Models\PerformanceCycleParticipant;
use App\Services\KpiAssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ParticipantKpiController extends Controller
{
    public function __construct(
        protected KpiAssignmentService $kpiAssignmentService
    ) {}

    /**
     * Get all KPIs for a specific participant with summary.
     */
    public function index(string $tenant, PerformanceCycleParticipant $participant): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $summary = $this->kpiAssignmentService->getParticipantKpiSummary($participant);

        // Load relationships for the KPIs
        $summary['kpis']->load(['kpiTemplate', 'progressEntries']);

        return response()->json([
            'total_kpis' => $summary['total_kpis'],
            'completed_kpis' => $summary['completed_kpis'],
            'pending_kpis' => $summary['pending_kpis'],
            'in_progress_kpis' => $summary['in_progress_kpis'],
            'weighted_average_achievement' => $summary['weighted_average_achievement'],
            'total_weight' => $summary['total_weight'],
            'kpis' => KpiAssignmentResource::collection($summary['kpis']),
        ]);
    }
}
