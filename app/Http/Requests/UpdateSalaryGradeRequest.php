<?php

namespace App\Http\Requests;

use App\Models\SalaryGrade;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalaryGradeRequest extends FormRequest
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
        $salaryGrade = $this->route('salary_grade');
        $salaryGradeId = $salaryGrade instanceof SalaryGrade ? $salaryGrade->id : $salaryGrade;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(SalaryGrade::class, 'name')->ignore($salaryGradeId),
            ],
            'minimum_salary' => ['required', 'numeric', 'min:0'],
            'midpoint_salary' => ['required', 'numeric', 'min:0', 'gte:minimum_salary'],
            'maximum_salary' => ['required', 'numeric', 'min:0', 'gte:midpoint_salary'],
            'currency' => ['nullable', 'string', 'max:3'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'steps' => ['nullable', 'array'],
            'steps.*.step_number' => ['required_with:steps', 'integer', 'min:1'],
            'steps.*.amount' => ['required_with:steps', 'numeric', 'min:0'],
            'steps.*.effective_date' => ['nullable', 'date'],
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
            'name.required' => 'The salary grade name is required.',
            'name.unique' => 'This salary grade name is already in use.',
            'minimum_salary.required' => 'The minimum salary is required.',
            'minimum_salary.numeric' => 'The minimum salary must be a number.',
            'midpoint_salary.required' => 'The midpoint salary is required.',
            'midpoint_salary.gte' => 'The midpoint salary must be greater than or equal to the minimum salary.',
            'maximum_salary.required' => 'The maximum salary is required.',
            'maximum_salary.gte' => 'The maximum salary must be greater than or equal to the midpoint salary.',
            'status.required' => 'The salary grade status is required.',
            'status.in' => 'The status must be either active or inactive.',
            'steps.*.step_number.required_with' => 'The step number is required for each step.',
            'steps.*.amount.required_with' => 'The amount is required for each step.',
        ];
    }
}
