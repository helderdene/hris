<?php

namespace App\Http\Requests;

use App\Models\KpiAssignment;
use App\Models\PositionCompetency;
use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluationResponseRequest extends FormRequest
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
            // Narrative feedback fields
            'strengths' => ['nullable', 'string', 'max:5000'],
            'areas_for_improvement' => ['nullable', 'string', 'max:5000'],
            'overall_comments' => ['nullable', 'string', 'max:5000'],
            'development_suggestions' => ['nullable', 'string', 'max:5000'],

            // Competency ratings
            'competency_ratings' => ['nullable', 'array'],
            'competency_ratings.*' => ['array'],
            'competency_ratings.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'competency_ratings.*.comments' => ['nullable', 'string', 'max:1000'],

            // KPI ratings (validated only if reviewer can view KPIs)
            'kpi_ratings' => ['nullable', 'array'],
            'kpi_ratings.*' => ['array'],
            'kpi_ratings.*.rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'kpi_ratings.*.comments' => ['nullable', 'string', 'max:1000'],

            // Submission flag
            'submit' => ['nullable', 'boolean'],
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
            'strengths.max' => 'The strengths feedback must not exceed 5000 characters.',
            'areas_for_improvement.max' => 'The areas for improvement feedback must not exceed 5000 characters.',
            'overall_comments.max' => 'The overall comments must not exceed 5000 characters.',
            'development_suggestions.max' => 'The development suggestions must not exceed 5000 characters.',
            'competency_ratings.*.rating.min' => 'Competency rating must be at least 1.',
            'competency_ratings.*.rating.max' => 'Competency rating must not exceed 5.',
            'competency_ratings.*.comments.max' => 'Competency comments must not exceed 1000 characters.',
            'kpi_ratings.*.rating.min' => 'KPI rating must be at least 1.',
            'kpi_ratings.*.rating.max' => 'KPI rating must not exceed 5.',
            'kpi_ratings.*.comments.max' => 'KPI comments must not exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $this->validateCompetencyIds($validator);
            $this->validateKpiIds($validator);
        });
    }

    /**
     * Validate that competency IDs exist.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    protected function validateCompetencyIds($validator): void
    {
        $competencyRatings = $this->input('competency_ratings', []);

        if (empty($competencyRatings)) {
            return;
        }

        $ids = array_keys($competencyRatings);
        $existingIds = PositionCompetency::whereIn('id', $ids)->pluck('id')->toArray();

        foreach ($ids as $id) {
            if (! in_array($id, $existingIds)) {
                $validator->errors()->add(
                    "competency_ratings.{$id}",
                    "The selected position competency (ID: {$id}) does not exist."
                );
            }
        }
    }

    /**
     * Validate that KPI assignment IDs exist.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    protected function validateKpiIds($validator): void
    {
        $kpiRatings = $this->input('kpi_ratings', []);

        if (empty($kpiRatings)) {
            return;
        }

        $ids = array_keys($kpiRatings);
        $existingIds = KpiAssignment::whereIn('id', $ids)->pluck('id')->toArray();

        foreach ($ids as $id) {
            if (! in_array($id, $existingIds)) {
                $validator->errors()->add(
                    "kpi_ratings.{$id}",
                    "The selected KPI assignment (ID: {$id}) does not exist."
                );
            }
        }
    }
}
