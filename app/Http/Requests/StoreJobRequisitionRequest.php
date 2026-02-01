<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\JobRequisitionUrgency;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequisitionRequest extends FormRequest
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
            'position_id' => ['required', 'integer', Rule::exists(Position::class, 'id')],
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'requested_by_employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'headcount' => ['required', 'integer', 'min:1', 'max:100'],
            'employment_type' => ['required', 'string', Rule::in(EmploymentType::values())],
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_range_min'],
            'justification' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'string', Rule::in(JobRequisitionUrgency::values())],
            'preferred_start_date' => ['nullable', 'date', 'after_or_equal:today'],
            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['string', 'max:500'],
            'remarks' => ['nullable', 'string', 'max:2000'],
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
            'position_id.required' => 'Please select a position.',
            'position_id.exists' => 'The selected position does not exist.',
            'department_id.required' => 'Please select a department.',
            'department_id.exists' => 'The selected department does not exist.',
            'requested_by_employee_id.required' => 'Please select the requesting employee.',
            'requested_by_employee_id.exists' => 'The selected employee does not exist.',
            'headcount.required' => 'Please specify the number of positions needed.',
            'headcount.min' => 'Headcount must be at least 1.',
            'employment_type.required' => 'Please select an employment type.',
            'justification.required' => 'Please provide a justification for this requisition.',
            'justification.max' => 'Justification cannot exceed 5000 characters.',
            'urgency.required' => 'Please select an urgency level.',
            'salary_range_max.gte' => 'Maximum salary must be greater than or equal to minimum salary.',
            'preferred_start_date.after_or_equal' => 'Preferred start date cannot be in the past.',
        ];
    }

    /**
     * Get the validated data with additional fields.
     *
     * @return array<string, mixed>
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();
        $validated['created_by'] = auth()->id();

        return $validated;
    }
}
