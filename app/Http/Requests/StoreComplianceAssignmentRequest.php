<?php

namespace App\Http\Requests;

use App\Models\ComplianceCourse;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplianceAssignmentRequest extends FormRequest
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
            'compliance_course_id' => ['required', 'integer', Rule::exists(ComplianceCourse::class, 'id')],
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'days_to_complete' => ['nullable', 'integer', 'min:1', 'max:365'],
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
            'compliance_course_id.required' => 'A compliance course must be selected.',
            'compliance_course_id.exists' => 'The selected compliance course does not exist.',
            'employee_id.required' => 'An employee must be selected.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'days_to_complete.min' => 'Days to complete must be at least 1.',
        ];
    }
}
