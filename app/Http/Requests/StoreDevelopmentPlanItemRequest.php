<?php

namespace App\Http\Requests;

use App\Enums\GoalPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevelopmentPlanItemRequest extends FormRequest
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
        return [
            'competency_id' => ['nullable', 'integer', 'exists:competencies,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'current_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'target_level' => ['nullable', 'integer', 'min:1', 'max:5', 'gte:current_level'],
            'priority' => ['nullable', Rule::in(GoalPriority::values())],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Please provide a title for this development area.',
            'target_level.gte' => 'Target level must be greater than or equal to current level.',
        ];
    }
}
