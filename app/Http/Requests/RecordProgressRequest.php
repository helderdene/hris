<?php

namespace App\Http\Requests;

use App\Enums\GoalStatus;
use App\Models\GoalKeyResult;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordProgressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal && $goal->status === GoalStatus::Active;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $goal = $this->route('goal');

        $rules = [
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($goal && $goal->isOkr()) {
            $rules['goal_key_result_id'] = [
                'required',
                'integer',
                Rule::exists(GoalKeyResult::class, 'id')->where('goal_id', $goal->id),
            ];
            $rules['progress_value'] = ['required', 'numeric'];
        } else {
            $rules['progress_percentage'] = ['required', 'numeric', 'min:0', 'max:100'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'goal_key_result_id.required' => 'Please select a key result to update.',
            'goal_key_result_id.exists' => 'The selected key result does not exist.',
            'progress_value.required' => 'Please enter a progress value.',
            'progress_value.numeric' => 'Progress value must be a number.',
            'progress_percentage.required' => 'Please enter a progress percentage.',
            'progress_percentage.numeric' => 'Progress percentage must be a number.',
            'progress_percentage.min' => 'Progress percentage cannot be negative.',
            'progress_percentage.max' => 'Progress percentage cannot exceed 100.',
        ];
    }
}
