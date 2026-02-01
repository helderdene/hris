<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSssContributionTableRequest extends FormRequest
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
            'effective_from' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'employee_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'employer_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'is_active' => ['boolean'],
            'brackets' => ['required', 'array', 'min:1'],
            'brackets.*.min_salary' => ['required', 'numeric', 'min:0'],
            'brackets.*.max_salary' => ['nullable', 'numeric', 'min:0', 'gt:brackets.*.min_salary'],
            'brackets.*.monthly_salary_credit' => ['required', 'numeric', 'min:0'],
            'brackets.*.employee_contribution' => ['required', 'numeric', 'min:0'],
            'brackets.*.employer_contribution' => ['required', 'numeric', 'min:0'],
            'brackets.*.total_contribution' => ['required', 'numeric', 'min:0'],
            'brackets.*.ec_contribution' => ['nullable', 'numeric', 'min:0'],
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
            'effective_from.required' => 'The effective date is required.',
            'brackets.required' => 'At least one contribution bracket is required.',
            'brackets.*.min_salary.required' => 'The minimum salary is required for each bracket.',
            'brackets.*.monthly_salary_credit.required' => 'The monthly salary credit is required for each bracket.',
            'brackets.*.employee_contribution.required' => 'The employee contribution is required for each bracket.',
            'brackets.*.employer_contribution.required' => 'The employer contribution is required for each bracket.',
            'brackets.*.total_contribution.required' => 'The total contribution is required for each bracket.',
        ];
    }
}
