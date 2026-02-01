<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\SalaryDisplayOption;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobRequisition;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobPostingRequest extends FormRequest
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
            'job_requisition_id' => ['nullable', 'integer', Rule::exists(JobRequisition::class, 'id')],
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists(Position::class, 'id')],
            'created_by_employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'requirements' => ['nullable', 'string'],
            'benefits' => ['nullable', 'string'],
            'employment_type' => ['required', 'string', Rule::in(EmploymentType::values())],
            'location' => ['required', 'string', 'max:255'],
            'salary_display_option' => ['nullable', 'string', Rule::in(SalaryDisplayOption::values())],
            'salary_range_min' => ['nullable', 'numeric', 'min:0'],
            'salary_range_max' => ['nullable', 'numeric', 'min:0', 'gte:salary_range_min'],
            'application_instructions' => ['nullable', 'string', 'max:5000'],
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
            'department_id.required' => 'Please select a department.',
            'department_id.exists' => 'The selected department does not exist.',
            'title.required' => 'Please provide a job title.',
            'description.required' => 'Please provide a job description.',
            'employment_type.required' => 'Please select an employment type.',
            'location.required' => 'Please specify the job location.',
            'salary_range_max.gte' => 'Maximum salary must be greater than or equal to minimum salary.',
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
