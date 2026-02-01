<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\JobRequisitionStatus;
use App\Enums\JobRequisitionUrgency;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobRequisitionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $requisition = $this->route('job_requisition');

        return $requisition && $requisition->status === JobRequisitionStatus::Draft;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'position_id' => ['sometimes', 'required', 'integer', Rule::exists(Position::class, 'id')],
            'department_id' => ['sometimes', 'required', 'integer', Rule::exists(Department::class, 'id')],
            'headcount' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
            'employment_type' => ['sometimes', 'required', 'string', Rule::in(EmploymentType::values())],
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_range_min'],
            'justification' => ['sometimes', 'required', 'string', 'max:5000'],
            'urgency' => ['sometimes', 'required', 'string', Rule::in(JobRequisitionUrgency::values())],
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
            'position_id.exists' => 'The selected position does not exist.',
            'department_id.exists' => 'The selected department does not exist.',
            'headcount.min' => 'Headcount must be at least 1.',
            'justification.max' => 'Justification cannot exceed 5000 characters.',
            'salary_range_max.gte' => 'Maximum salary must be greater than or equal to minimum salary.',
            'preferred_start_date.after_or_equal' => 'Preferred start date cannot be in the past.',
        ];
    }
}
