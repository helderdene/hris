<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalibrateEvaluationRequest extends FormRequest
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
            'final_competency_score' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'final_kpi_score' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'final_overall_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'final_rating' => [
                'nullable',
                'string',
                Rule::in([
                    'exceptional',
                    'exceeds_expectations',
                    'meets_expectations',
                    'needs_improvement',
                    'unsatisfactory',
                ]),
            ],
            'calibration_notes' => ['nullable', 'string', 'max:5000'],
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
            'final_competency_score.min' => 'The competency score must be at least 1.',
            'final_competency_score.max' => 'The competency score must not exceed 5.',
            'final_kpi_score.min' => 'The KPI score cannot be negative.',
            'final_kpi_score.max' => 'The KPI score must not exceed 200%.',
            'final_overall_score.min' => 'The overall score cannot be negative.',
            'final_overall_score.max' => 'The overall score must not exceed 100.',
            'final_rating.in' => 'The selected rating is invalid.',
            'calibration_notes.max' => 'Calibration notes must not exceed 5000 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'final_competency_score' => 'final competency score',
            'final_kpi_score' => 'final KPI score',
            'final_overall_score' => 'final overall score',
            'final_rating' => 'final rating',
            'calibration_notes' => 'calibration notes',
        ];
    }
}
