<?php

namespace App\Http\Requests;

use App\Models\KpiTemplate;
use App\Models\PerformanceCycleParticipant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkAssignKpiRequest extends FormRequest
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
            'kpi_template_id' => [
                'required',
                'integer',
                Rule::exists(KpiTemplate::class, 'id'),
            ],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => [
                'required',
                'integer',
                Rule::exists(PerformanceCycleParticipant::class, 'id'),
            ],
            'target_value' => ['required', 'numeric', 'min:0'],
            'weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
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
            'kpi_template_id.required' => 'Please select a KPI template.',
            'kpi_template_id.exists' => 'The selected KPI template does not exist.',
            'participant_ids.required' => 'Please select at least one participant.',
            'participant_ids.array' => 'Participant IDs must be provided as an array.',
            'participant_ids.min' => 'Please select at least one participant.',
            'participant_ids.*.exists' => 'One or more selected participants do not exist.',
            'target_value.required' => 'The target value is required.',
            'target_value.min' => 'The target value must be a positive number.',
            'weight.min' => 'The weight must be a positive number.',
            'weight.max' => 'The weight must not exceed 10.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('weight')) {
            $this->merge(['weight' => 1.00]);
        }
    }
}
