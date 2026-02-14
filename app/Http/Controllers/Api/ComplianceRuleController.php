<?php

namespace App\Http\Controllers\Api;

use App\Enums\ComplianceRuleType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplianceRuleRequest;
use App\Http\Requests\UpdateComplianceRuleRequest;
use App\Http\Resources\ComplianceRuleResource;
use App\Models\ComplianceAssignmentRule;
use App\Models\ComplianceCourse;
use App\Services\ComplianceAssignmentService;
use App\Services\ComplianceRuleEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ComplianceRuleController extends Controller
{
    public function __construct(
        protected ComplianceRuleEngine $ruleEngine,
        protected ComplianceAssignmentService $assignmentService
    ) {}

    /**
     * Display a listing of rules for a compliance course.
     */
    public function index(
        Request $request,
        ComplianceCourse $complianceCourse
    ): AnonymousResourceCollection {
        Gate::authorize('can-manage-training');

        $query = $complianceCourse->assignmentRules()
            ->with(['creator'])
            ->orderByDesc('priority');

        if ($request->boolean('active_only')) {
            $query->active();
        }

        $rules = $query->get();

        return ComplianceRuleResource::collection($rules);
    }

    /**
     * Store a newly created rule.
     */
    public function store(
        StoreComplianceRuleRequest $request,
        ComplianceCourse $complianceCourse
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $validated['compliance_course_id'] = $complianceCourse->id;
        $validated['created_by'] = auth()->user()->employee?->id;

        // Ensure conditions is always set (required for JSON column)
        if (! isset($validated['conditions'])) {
            $validated['conditions'] = [];
        }

        $rule = ComplianceAssignmentRule::create($validated);

        // Apply to existing employees if specified
        if ($validated['apply_to_existing'] ?? false) {
            $this->applyRuleToExistingEmployees($rule);
        }

        $rule->load('creator');

        return (new ComplianceRuleResource($rule))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified rule.
     */
    public function show(
        ComplianceCourse $complianceCourse,
        ComplianceAssignmentRule $rule
    ): ComplianceRuleResource {
        Gate::authorize('can-manage-training');

        // Ensure rule belongs to the course
        if ($rule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        $rule->load(['creator', 'assignments' => function ($q) {
            $q->limit(10)->latest();
        }]);

        return new ComplianceRuleResource($rule);
    }

    /**
     * Update the specified rule.
     */
    public function update(
        UpdateComplianceRuleRequest $request,
        ComplianceCourse $complianceCourse,
        ComplianceAssignmentRule $rule
    ): ComplianceRuleResource {
        Gate::authorize('can-manage-training');

        // Ensure rule belongs to the course
        if ($rule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        $validated = $request->validated();
        $rule->update($validated);

        $rule->load('creator');

        return new ComplianceRuleResource($rule);
    }

    /**
     * Remove the specified rule.
     */
    public function destroy(
        ComplianceCourse $complianceCourse,
        ComplianceAssignmentRule $rule
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        // Ensure rule belongs to the course
        if ($rule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        $rule->delete();

        return response()->json([
            'message' => 'Rule deleted successfully.',
        ]);
    }

    /**
     * Preview employees that would be affected by a rule.
     */
    public function preview(
        Request $request,
        ComplianceCourse $complianceCourse
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        $request->validate([
            'rule_type' => ['required', 'string', 'in:'.implode(',', ComplianceRuleType::values())],
            'conditions' => ['required', 'array'],
        ]);

        $ruleType = ComplianceRuleType::from($request->input('rule_type'));
        $conditions = $request->input('conditions');

        $employees = $this->ruleEngine->previewRuleEmployees($ruleType, $conditions);

        return response()->json([
            'employee_count' => $employees->count(),
            'employees' => $employees->take(50)->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'employee_number' => $employee->employee_number,
                    'full_name' => $employee->full_name,
                    'department' => $employee->department?->name,
                    'position' => $employee->position?->title,
                ];
            }),
        ]);
    }

    /**
     * Apply a rule to existing employees now.
     */
    public function apply(
        ComplianceCourse $complianceCourse,
        ComplianceAssignmentRule $rule
    ): JsonResponse {
        Gate::authorize('can-manage-training');

        // Ensure rule belongs to the course
        if ($rule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        $assignmentsCreated = $this->applyRuleToExistingEmployees($rule);

        return response()->json([
            'message' => "Rule applied successfully. {$assignmentsCreated} new assignments created.",
            'assignments_created' => $assignmentsCreated,
        ]);
    }

    /**
     * Toggle rule active status.
     */
    public function toggle(
        ComplianceCourse $complianceCourse,
        ComplianceAssignmentRule $rule
    ): ComplianceRuleResource {
        Gate::authorize('can-manage-training');

        // Ensure rule belongs to the course
        if ($rule->compliance_course_id !== $complianceCourse->id) {
            abort(404);
        }

        $rule->update(['is_active' => ! $rule->is_active]);
        $rule->load('creator');

        return new ComplianceRuleResource($rule);
    }

    /**
     * Apply a rule to all existing matching employees.
     */
    protected function applyRuleToExistingEmployees(ComplianceAssignmentRule $rule): int
    {
        $employees = $this->ruleEngine->getAffectedEmployees($rule);
        $created = 0;

        foreach ($employees as $employee) {
            $assignment = $this->assignmentService->assignByRule($rule, $employee);
            if ($assignment && $assignment->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * Get available rule types with descriptions.
     */
    public function ruleTypes(): JsonResponse
    {
        $types = collect(ComplianceRuleType::cases())->map(function ($type) {
            return [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
                'requires_conditions' => $type->requiresConditions(),
                'condition_field' => $type->conditionField(),
            ];
        });

        return response()->json($types);
    }
}
