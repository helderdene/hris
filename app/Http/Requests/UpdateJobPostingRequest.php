<?php

namespace App\Http\Requests;

use App\Enums\EmploymentType;
use App\Enums\SalaryDisplayOption;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobPostingRequest extends FormRequest
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
            'department_id' => ['sometimes', 'required', 'integer', Rule::exists(Department::class, 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists(Position::class, 'id')],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'requirements' => ['nullable', 'string'],
            'benefits' => ['nullable', 'string'],
            'employment_type' => ['sometimes', 'required', 'string', Rule::in(EmploymentType::values())],
            'location' => ['sometimes', 'required', 'string', 'max:255'],
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
            'title.required' => 'Please provide a job title.',
            'description.required' => 'Please provide a job description.',
            'employment_type.required' => 'Please select an employment type.',
            'location.required' => 'Please specify the job location.',
            'salary_range_max.gte' => 'Maximum salary must be greater than or equal to minimum salary.',
        ];
    }
}
