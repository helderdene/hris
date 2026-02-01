<?php

namespace App\Http\Controllers\My;

use App\Enums\DevelopmentActivityType;
use App\Enums\DevelopmentItemStatus;
use App\Enums\DevelopmentPlanStatus;
use App\Enums\GoalPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDevelopmentActivityRequest;
use App\Http\Requests\StoreDevelopmentPlanCheckInRequest;
use App\Http\Requests\StoreDevelopmentPlanItemRequest;
use App\Http\Requests\StoreDevelopmentPlanRequest;
use App\Http\Requests\UpdateDevelopmentPlanRequest;
use App\Http\Resources\DevelopmentPlanListResource;
use App\Http\Resources\DevelopmentPlanResource;
use App\Models\DevelopmentActivity;
use App\Models\DevelopmentPlan;
use App\Models\DevelopmentPlanItem;
use App\Models\Employee;
use App\Models\PerformanceCycleParticipant;
use App\Services\DevelopmentPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyDevelopmentPlanController extends Controller
{
    public function __construct(
        protected DevelopmentPlanService $developmentPlanService
    ) {}

    /**
     * Display the employee's development plans.
     */
    public function index(Request $request): Response
    {
        $employee = $this->getCurrentEmployee($request);

        $query = DevelopmentPlan::forEmployee($employee->id)
            ->with(['items'])
            ->withCount('items')
            ->orderBy('created_at', 'desc');

        // Apply status filter
        if ($request->filled('status')) {
            $query->byStatus($request->input('status'));
        }

        $plans = $query->paginate(15)->withQueryString();
        $statistics = $this->developmentPlanService->getStatistics($employee);

        return Inertia::render('My/DevelopmentPlans/Index', [
            'plans' => DevelopmentPlanListResource::collection($plans),
            'statistics' => $statistics,
            'statuses' => $this->getStatusOptions(),
            'filters' => [
                'status' => $request->input('status'),
            ],
        ]);
    }

    /**
     * Display the create plan page.
     */
    public function create(Request $request): Response
    {
        $employee = $this->getCurrentEmployee($request);

        $fromEvaluation = null;
        $evaluationGaps = [];

        if ($request->filled('from_evaluation')) {
            $participant = PerformanceCycleParticipant::with(['performanceCycleInstance', 'employee'])
                ->where('id', $request->input('from_evaluation'))
                ->where('employee_id', $employee->id)
                ->first();

            if ($participant !== null) {
                $fromEvaluation = [
                    'id' => $participant->id,
                    'instance_name' => $participant->performanceCycleInstance?->name,
                ];
                $evaluationGaps = $this->developmentPlanService->getCompetencyGaps($participant);
            }
        }

        return Inertia::render('My/DevelopmentPlans/Create', [
            'priorities' => $this->getPriorityOptions(),
            'activityTypes' => $this->getActivityTypeOptions(),
            'fromEvaluation' => $fromEvaluation,
            'evaluationGaps' => $evaluationGaps,
            'manager' => $employee->supervisor ? [
                'id' => $employee->supervisor->id,
                'full_name' => $employee->supervisor->full_name,
            ] : null,
        ]);
    }

    /**
     * Store a new development plan.
     */
    public function store(StoreDevelopmentPlanRequest $request): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);
        $user = $request->user();

        $participant = null;
        if ($request->filled('from_evaluation')) {
            $participant = PerformanceCycleParticipant::where('id', $request->input('from_evaluation'))
                ->where('employee_id', $employee->id)
                ->first();
        }

        if ($participant !== null && $request->boolean('auto_populate_gaps', false)) {
            $result = $this->developmentPlanService->createFromEvaluationGaps(
                $participant,
                $user,
                $request->input('title')
            );
            $plan = $result['plan'];
        } else {
            $plan = $this->developmentPlanService->createPlan(
                $employee,
                $request->validated(),
                $user,
                $participant
            );
        }

        $plan->load(['items.activities', 'checkIns.createdByUser', 'employee', 'manager']);

        return response()->json([
            'message' => 'Development plan created successfully.',
            'plan' => new DevelopmentPlanResource($plan),
        ], 201);
    }

    /**
     * Display a specific development plan.
     */
    public function show(Request $request, string $tenant, DevelopmentPlan $developmentPlan): Response
    {
        $employee = $this->getCurrentEmployee($request);

        if ($developmentPlan->employee_id !== $employee->id) {
            abort(403, 'You can only view your own development plans.');
        }

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

        return Inertia::render('My/DevelopmentPlans/Show', [
            'plan' => new DevelopmentPlanResource($developmentPlan),
            'statuses' => $this->getStatusOptions(),
            'itemStatuses' => $this->getItemStatusOptions(),
            'priorities' => $this->getPriorityOptions(),
            'activityTypes' => $this->getActivityTypeOptions(),
        ]);
    }

    /**
     * Update a development plan.
     */
    public function update(UpdateDevelopmentPlanRequest $request, string $tenant, DevelopmentPlan $developmentPlan): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        if ($developmentPlan->employee_id !== $employee->id) {
            abort(403, 'You can only update your own development plans.');
        }

        if (! $developmentPlan->isEditable()) {
            abort(422, 'This plan cannot be edited in its current status.');
        }

        $developmentPlan->update($request->validated());
        $developmentPlan->load(['items.activities', 'checkIns.createdByUser']);

        return response()->json([
            'message' => 'Development plan updated successfully.',
            'plan' => new DevelopmentPlanResource($developmentPlan),
        ]);
    }

    /**
     * Submit the plan for approval.
     */
    public function submit(Request $request, string $tenant, DevelopmentPlan $developmentPlan): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        if ($developmentPlan->employee_id !== $employee->id) {
            abort(403, 'You can only submit your own development plans.');
        }

        if ($developmentPlan->status !== DevelopmentPlanStatus::Draft) {
            abort(422, 'Only draft plans can be submitted for approval.');
        }

        $developmentPlan->submit();

        return response()->json([
            'message' => 'Development plan submitted for approval.',
            'plan' => new DevelopmentPlanResource($developmentPlan->fresh()),
        ]);
    }

    /**
     * Add an item to the development plan.
     */
    public function addItem(StoreDevelopmentPlanItemRequest $request, string $tenant, DevelopmentPlan $developmentPlan): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        if ($developmentPlan->employee_id !== $employee->id) {
            abort(403, 'You can only add items to your own development plans.');
        }

        $item = $this->developmentPlanService->addItem($developmentPlan, $request->validated());
        $item->load('competency');

        return response()->json([
            'message' => 'Development item added successfully.',
            'item' => new \App\Http\Resources\DevelopmentPlanItemResource($item),
        ], 201);
    }

    /**
     * Update a development plan item.
     */
    public function updateItem(StoreDevelopmentPlanItemRequest $request, string $tenant, DevelopmentPlan $developmentPlan, DevelopmentPlanItem $item): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        if ($developmentPlan->employee_id !== $employee->id) {
            abort(403, 'You can only update items in your own development plans.');
        }

        if ($item->development_plan_id !== $developmentPlan->id) {
            abort(404, 'Item not found in this development plan.');
        }

        $item = $this->developmentPlanService->updateItem($item, $request->validated());
        $item->load(['competency', 'activities']);

        return response()->json([
            'message' => 'Development item updated successfully.',
            'item' => new \App\Http\Resources\DevelopmentPlanItemResource($item),
        ]);
    }

    /**
     * Add an activity to a development plan item.
     */
    public function addActivity(StoreDevelopmentActivityRequest $request, string $tenant, DevelopmentPlan $developmentPlan, DevelopmentPlanItem $item): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        if ($developmentPlan->employee_id !== $employee->id) {
            abort(403, 'You can only add activities to your own development plans.');
        }

        if ($item->development_plan_id !== $developmentPlan->id) {
            abort(404, 'Item not found in this development plan.');
        }

        $activity = $this->developmentPlanService->addActivity($item, $request->validated());

        return response()->json([
            'message' => 'Activity added successfully.',
            'activity' => new \App\Http\Resources\DevelopmentActivityResource($activity),
        ], 201);
    }

    /**
     * Update an activity.
     */
    public function updateActivity(StoreDevelopmentActivityRequest $request, string $tenant, DevelopmentActivity $activity): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);
        $plan = $activity->developmentPlanItem->developmentPlan;

        if ($plan->employee_id !== $employee->id) {
            abort(403, 'You can only update activities in your own development plans.');
        }

        $activity = $this->developmentPlanService->updateActivity($activity, $request->validated());

        return response()->json([
            'message' => 'Activity updated successfully.',
            'activity' => new \App\Http\Resources\DevelopmentActivityResource($activity),
        ]);
    }

    /**
     * Complete an activity.
     */
    public function completeActivity(Request $request, string $tenant, DevelopmentActivity $activity): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);
        $plan = $activity->developmentPlanItem->developmentPlan;

        if ($plan->employee_id !== $employee->id) {
            abort(403, 'You can only complete activities in your own development plans.');
        }

        $notes = $request->input('notes');
        $activity = $this->developmentPlanService->completeActivity($activity, $notes);

        return response()->json([
            'message' => 'Activity completed successfully.',
            'activity' => new \App\Http\Resources\DevelopmentActivityResource($activity),
        ]);
    }

    /**
     * Add a check-in to the development plan.
     */
    public function addCheckIn(StoreDevelopmentPlanCheckInRequest $request, string $tenant, DevelopmentPlan $developmentPlan): JsonResponse
    {
        $employee = $this->getCurrentEmployee($request);

        if ($developmentPlan->employee_id !== $employee->id) {
            abort(403, 'You can only add check-ins to your own development plans.');
        }

        $checkIn = $this->developmentPlanService->addCheckIn(
            $developmentPlan,
            $request->validated(),
            $request->user()
        );
        $checkIn->load('createdByUser');

        return response()->json([
            'message' => 'Check-in recorded successfully.',
            'check_in' => new \App\Http\Resources\DevelopmentPlanCheckInResource($checkIn),
        ], 201);
    }

    /**
     * Get the current employee from the authenticated user.
     */
    protected function getCurrentEmployee(Request $request): Employee
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if ($employee === null) {
            abort(403, 'You do not have an employee profile.');
        }

        return $employee;
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

    /**
     * Get item status options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getItemStatusOptions(): array
    {
        return array_map(
            fn (DevelopmentItemStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'description' => $status->description(),
                'color' => $status->colorClass(),
            ],
            DevelopmentItemStatus::cases()
        );
    }

    /**
     * Get priority options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string}>
     */
    private function getPriorityOptions(): array
    {
        return array_map(
            fn (GoalPriority $priority) => [
                'value' => $priority->value,
                'label' => $priority->label(),
                'description' => $priority->description(),
                'color' => $priority->colorClass(),
            ],
            [GoalPriority::High, GoalPriority::Medium, GoalPriority::Low]
        );
    }

    /**
     * Get activity type options.
     *
     * @return array<int, array{value: string, label: string, description: string, color: string, icon: string}>
     */
    private function getActivityTypeOptions(): array
    {
        return array_map(
            fn (DevelopmentActivityType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'color' => $type->colorClass(),
                'icon' => $type->icon(),
            ],
            DevelopmentActivityType::cases()
        );
    }
}
