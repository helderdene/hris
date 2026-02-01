<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProbationaryEvaluationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $evaluation = $this->route('probationary_evaluation');

        return $evaluation && $evaluation->can_be_edited;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'criteria_ratings' => ['nullable', 'array'],
            'criteria_ratings.*.criteria_id' => ['required', 'integer'],
            'criteria_ratings.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'criteria_ratings.*.comments' => ['nullable', 'string', 'max:1000'],
            'overall_rating' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'areas_for_improvement' => ['nullable', 'string', 'max:5000'],
            'manager_comments' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['nullable', 'string', 'in:recommend,recommend_with_conditions,extend_probation,not_recommend'],
            'recommendation_conditions' => ['nullable', 'string', 'max:5000'],
            'extension_months' => ['nullable', 'integer', 'min:1', 'max:6'],
            'recommendation_reason' => ['nullable', 'string', 'max:5000'],
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
            'criteria_ratings.*.rating.min' => 'Rating must be at least 1.',
            'criteria_ratings.*.rating.max' => 'Rating cannot exceed 5.',
            'overall_rating.min' => 'Overall rating must be at least 1.',
            'overall_rating.max' => 'Overall rating cannot exceed 5.',
            'extension_months.min' => 'Extension must be at least 1 month.',
            'extension_months.max' => 'Extension cannot exceed 6 months.',
        ];
    }
}
