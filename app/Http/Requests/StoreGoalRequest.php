<?php

namespace App\Http\Requests;

use App\Enums\GoalPriority;
use App\Enums\GoalType;
use App\Enums\GoalVisibility;
use App\Enums\KeyResultMetricType;
use App\Models\Employee;
use App\Models\Goal;
use App\Models\PerformanceCycleInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // employee_id is optional for employee self-service routes (EmployeeGoalController)
        // but required for admin routes (GoalController)
        $employeeIdRules = $this->isEmployeeSelfServiceRoute()
            ? ['nullable', 'integer', Rule::exists(Employee::class, 'id')]
            : ['required', 'integer', Rule::exists(Employee::class, 'id')];

        return [
            'employee_id' => $employeeIdRules,
            'performance_cycle_instance_id' => ['nullable', 'integer', Rule::exists(PerformanceCycleInstance::class, 'id')],
            'parent_goal_id' => ['nullable', 'integer', Rule::exists(Goal::class, 'id')],
            'goal_type' => ['required', 'string', Rule::in(GoalType::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'visibility' => ['required', 'string', Rule::in(GoalVisibility::values())],
            'priority' => ['required', 'string', Rule::in(GoalPriority::values())],
            'start_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:start_date'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'owner_notes' => ['nullable', 'string', 'max:2000'],
            'requires_approval' => ['nullable', 'boolean'],

            // Key results for OKR type (only validated when goal_type is okr_objective)
            'key_results' => $this->input('goal_type') === 'okr_objective'
                ? ['required', 'array', 'min:1', 'max:10']
                : ['nullable', 'array'],
            'key_results.*.title' => ['required_with:key_results', 'string', 'max:255'],
            'key_results.*.description' => ['nullable', 'string', 'max:1000'],
            'key_results.*.metric_type' => ['required_with:key_results', 'string', Rule::in(KeyResultMetricType::values())],
            'key_results.*.metric_unit' => ['nullable', 'string', 'max:50'],
            'key_results.*.target_value' => ['required_with:key_results', 'numeric'],
            'key_results.*.starting_value' => ['nullable', 'numeric'],
            'key_results.*.weight' => ['nullable', 'numeric', 'min:0', 'max:10'],

            // Milestones for SMART type (only validated when goal_type is smart_goal)
            'milestones' => $this->input('goal_type') === 'smart_goal'
                ? ['required', 'array', 'min:1', 'max:20']
                : ['nullable', 'array'],
            'milestones.*.title' => ['required_with:milestones', 'string', 'max:255'],
            'milestones.*.description' => ['nullable', 'string', 'max:1000'],
            'milestones.*.due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'goal_type.required' => 'Please select a goal type.',
            'goal_type.in' => 'Invalid goal type selected.',
            'title.required' => 'Please enter a goal title.',
            'title.max' => 'Goal title cannot exceed 255 characters.',
            'visibility.required' => 'Please select a visibility level.',
            'priority.required' => 'Please select a priority level.',
            'start_date.required' => 'Please select a start date.',
            'due_date.required' => 'Please select a due date.',
            'due_date.after_or_equal' => 'Due date must be on or after the start date.',
            'key_results.required_if' => 'OKR objectives require at least one key result.',
            'key_results.min' => 'Please add at least one key result.',
            'key_results.*.title.required_with' => 'Each key result must have a title.',
            'key_results.*.metric_type.required_with' => 'Each key result must have a metric type.',
            'key_results.*.target_value.required_with' => 'Each key result must have a target value.',
            'milestones.required_if' => 'SMART goals require at least one milestone.',
            'milestones.min' => 'Please add at least one milestone.',
            'milestones.*.title.required_with' => 'Each milestone must have a title.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $this->validateParentGoalAlignment($validator);
        });
    }

    /**
     * Validate parent goal alignment logic.
     */
    protected function validateParentGoalAlignment($validator): void
    {
        $parentGoalId = $this->input('parent_goal_id');

        if ($parentGoalId === null) {
            return;
        }

        $parentGoal = Goal::find($parentGoalId);

        if ($parentGoal === null) {
            return;
        }

        // Parent goal must be active or draft
        if ($parentGoal->status->isTerminal()) {
            $validator->errors()->add('parent_goal_id', 'Cannot align to a completed or cancelled goal.');
        }
    }

    /**
     * Check if the request is from employee self-service route.
     */
    protected function isEmployeeSelfServiceRoute(): bool
    {
        return $this->routeIs('api.my.goals.*') || $this->routeIs('api.my.goals.store');
    }
}
