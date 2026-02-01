<?php

namespace App\Http\Requests;

use App\Enums\ProbationaryEvaluationStatus;
use App\Enums\RegularizationRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class SubmitProbationaryEvaluationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $evaluation = $this->route('probationary_evaluation');

        return $evaluation && in_array($evaluation->status, [
            ProbationaryEvaluationStatus::Draft,
            ProbationaryEvaluationStatus::RevisionRequested,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $evaluation = $this->route('probationary_evaluation');
        $isFinalEvaluation = $evaluation && $evaluation->milestone->isFinalEvaluation();

        $rules = [
            'criteria_ratings' => ['required', 'array', 'min:1'],
            'criteria_ratings.*.criteria_id' => ['required', 'integer'],
            'criteria_ratings.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
            'criteria_ratings.*.comments' => ['nullable', 'string', 'max:1000'],
            'overall_rating' => ['required', 'numeric', 'min:1', 'max:5'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'areas_for_improvement' => ['nullable', 'string', 'max:5000'],
            'manager_comments' => ['nullable', 'string', 'max:5000'],
        ];

        // Recommendation is required only for final evaluation
        if ($isFinalEvaluation) {
            $rules['recommendation'] = ['required', new Enum(RegularizationRecommendation::class)];
            $rules['recommendation_conditions'] = ['required_if:recommendation,recommend_with_conditions', 'nullable', 'string', 'max:5000'];
            $rules['extension_months'] = ['required_if:recommendation,extend_probation', 'nullable', 'integer', 'min:1', 'max:6'];
            $rules['recommendation_reason'] = ['required_if:recommendation,not_recommend', 'nullable', 'string', 'max:5000'];
        } else {
            $rules['recommendation'] = ['nullable', new Enum(RegularizationRecommendation::class)];
            $rules['recommendation_conditions'] = ['nullable', 'string', 'max:5000'];
            $rules['extension_months'] = ['nullable', 'integer', 'min:1', 'max:6'];
            $rules['recommendation_reason'] = ['nullable', 'string', 'max:5000'];
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
            'criteria_ratings.required' => 'All criteria must be rated before submission.',
            'criteria_ratings.*.rating.required' => 'Each criterion must have a rating.',
            'criteria_ratings.*.rating.min' => 'Rating must be at least 1.',
            'criteria_ratings.*.rating.max' => 'Rating cannot exceed 5.',
            'overall_rating.required' => 'Overall rating is required.',
            'overall_rating.min' => 'Overall rating must be at least 1.',
            'overall_rating.max' => 'Overall rating cannot exceed 5.',
            'recommendation.required' => 'Regularization recommendation is required for final evaluation.',
            'recommendation_conditions.required_if' => 'Conditions are required when recommending with conditions.',
            'extension_months.required_if' => 'Extension period is required when extending probation.',
            'extension_months.min' => 'Extension must be at least 1 month.',
            'extension_months.max' => 'Extension cannot exceed 6 months.',
            'recommendation_reason.required_if' => 'Reason is required when not recommending regularization.',
        ];
    }
}
