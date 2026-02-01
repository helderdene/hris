<?php

namespace App\Http\Requests;

use App\Enums\GoalPriority;
use App\Enums\GoalVisibility;
use App\Models\Goal;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGoalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal && $goal->status->isEditable();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_goal_id' => ['nullable', 'integer', Rule::exists(Goal::class, 'id')],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:100'],
            'visibility' => ['sometimes', 'required', 'string', Rule::in(GoalVisibility::values())],
            'priority' => ['sometimes', 'required', 'string', Rule::in(GoalPriority::values())],
            'start_date' => ['sometimes', 'required', 'date'],
            'due_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'owner_notes' => ['nullable', 'string', 'max:2000'],
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
            'title.required' => 'Please enter a goal title.',
            'title.max' => 'Goal title cannot exceed 255 characters.',
            'visibility.required' => 'Please select a visibility level.',
            'priority.required' => 'Please select a priority level.',
            'start_date.required' => 'Please select a start date.',
            'due_date.required' => 'Please select a due date.',
            'due_date.after_or_equal' => 'Due date must be on or after the start date.',
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
            $this->validateNoCircularReference($validator);
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

        if ($parentGoal->status->isTerminal()) {
            $validator->errors()->add('parent_goal_id', 'Cannot align to a completed or cancelled goal.');
        }
    }

    /**
     * Validate no circular reference in goal hierarchy.
     */
    protected function validateNoCircularReference($validator): void
    {
        $parentGoalId = $this->input('parent_goal_id');
        $goal = $this->route('goal');

        if ($parentGoalId === null || $goal === null) {
            return;
        }

        if ($parentGoalId == $goal->id) {
            $validator->errors()->add('parent_goal_id', 'A goal cannot be its own parent.');

            return;
        }

        // Check if the parent goal is a descendant of this goal
        $currentParent = Goal::find($parentGoalId);
        $visited = [$goal->id];

        while ($currentParent !== null) {
            if (in_array($currentParent->id, $visited)) {
                $validator->errors()->add('parent_goal_id', 'This would create a circular reference in the goal hierarchy.');

                return;
            }

            $visited[] = $currentParent->id;
            $currentParent = $currentParent->parentGoal;
        }
    }
}
