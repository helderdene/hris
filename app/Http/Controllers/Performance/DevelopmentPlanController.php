<?php

namespace App\Http\Controllers\Performance;

use App\Enums\DevelopmentPlanStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveDevelopmentPlanRequest;
use App\Http\Resources\DevelopmentPlanListResource;
use App\Http\Resources\DevelopmentPlanResource;
use App\Models\DevelopmentPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for HR/Manager view of development plans.
 */
class DevelopmentPlanController extends Controller
{
    /**
     * Display all development plans (HR/Manager view).
     */
    public function index(Request $request): Response
    {
        $query = DevelopmentPlan::with(['employee.position', 'employee.department', 'manager'])
            ->withCount('items')
            ->orderBy('updated_at', 'desc');

        // Apply status filter
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        // Apply employee filter
        if ($request->filled('employee_id')) {
            $query->forEmployee($request->input('employee_id'));
        }

        // Filter by pending approval for managers
        if ($request->boolean('pending_only')) {
            $query->pending();
        }

        $plans = $query->paginate(20)->withQueryString();

        // Get pending count for notification badge
        $pendingCount = DevelopmentPlan::pending()->count();

        return Inertia::render('Performance/DevelopmentPlans/Index', [
            'plans' => DevelopmentPlanListResource::collection($plans),
            'pendingCount' => $pendingCount,
            'statuses' => $this->getStatusOptions(),
            'filters' => [
                'status' => $request->input('status'),
                'employee_id' => $request->input('employee_id'),
                'pending_only' => $request->boolean('pending_only'),
            ],
        ]);
    }

    /**
     * Display a specific development plan (HR/Manager view).
     */
    public function show(Request $request, DevelopmentPlan $developmentPlan): Response
    {
        $developmentPlan->load([
            'items.activities',
            'items.competency',
            'checkIns.createdByUser',
            'employee.position',
            'employee.department',
            'manager',
            'approvedByUser',
            'createdByUser',
            'performanceCycleParticipant.performanceCycleInstance',
        ]);

        return Inertia::render('Performance/DevelopmentPlans/Show', [
            'plan' => new DevelopmentPlanResource($developmentPlan),
            'canApprove' => $developmentPlan->status === DevelopmentPlanStatus::PendingApproval,
        ]);
    }

    /**
     * Approve a development plan.
     */
    public function approve(ApproveDevelopmentPlanRequest $request, DevelopmentPlan $developmentPlan): JsonResponse
    {
        if ($developmentPlan->status !== DevelopmentPlanStatus::PendingApproval) {
            abort(422, 'This plan is not pending approval.');
        }

        $developmentPlan->approve($request->user(), $request->input('notes'));

        return response()->json([
            'message' => 'Development plan approved successfully.',
            'plan' => new DevelopmentPlanResource($developmentPlan->fresh()),
        ]);
    }

    /**
     * Reject a development plan.
     */
    public function reject(ApproveDevelopmentPlanRequest $request, DevelopmentPlan $developmentPlan): JsonResponse
    {
        if ($developmentPlan->status !== DevelopmentPlanStatus::PendingApproval) {
            abort(422, 'This plan is not pending approval.');
        }

        $developmentPlan->reject($request->user(), $request->input('notes'));

        return response()->json([
            'message' => 'Development plan returned to employee for revision.',
            'plan' => new DevelopmentPlanResource($developmentPlan->fresh()),
        ]);
    }

    /**
     * Get status options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getStatusOptions(): array
    {
        return array_map(
            fn (DevelopmentPlanStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->colorClass(),
            ],
            DevelopmentPlanStatus::cases()
        );
    }
}
