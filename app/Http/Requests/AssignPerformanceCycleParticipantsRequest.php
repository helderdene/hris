<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignPerformanceCycleParticipantsRequest extends FormRequest
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
            'excluded_employee_ids' => ['array'],
            'excluded_employee_ids.*' => ['integer', 'exists:tenant.employees,id'],
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
            'excluded_employee_ids.array' => 'Excluded employee IDs must be provided as an array.',
            'excluded_employee_ids.*.integer' => 'Each excluded employee ID must be an integer.',
            'excluded_employee_ids.*.exists' => 'One or more excluded employees do not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('excluded_employee_ids')) {
            $this->merge(['excluded_employee_ids' => []]);
        }
    }
}
