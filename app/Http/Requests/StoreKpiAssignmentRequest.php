<?php

namespace App\Http\Requests;

use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\PerformanceCycleParticipant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKpiAssignmentRequest extends FormRequest
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
            'performance_cycle_participant_id' => [
                'required',
                'integer',
                Rule::exists(PerformanceCycleParticipant::class, 'id'),
            ],
            'target_value' => ['required', 'numeric', 'min:0'],
            'weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'notes' => ['nullable', 'string'],
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
            'performance_cycle_participant_id.required' => 'Please select a participant.',
            'performance_cycle_participant_id.exists' => 'The selected participant does not exist.',
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

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate assignment
            if ($this->filled(['kpi_template_id', 'performance_cycle_participant_id'])) {
                $exists = KpiAssignment::where('kpi_template_id', $this->kpi_template_id)
                    ->where('performance_cycle_participant_id', $this->performance_cycle_participant_id)
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'kpi_template_id',
                        'This KPI is already assigned to the selected participant.'
                    );
                }
            }
        });
    }
}
