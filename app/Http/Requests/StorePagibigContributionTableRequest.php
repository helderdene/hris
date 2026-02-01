<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePagibigContributionTableRequest extends FormRequest
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
            'max_monthly_compensation' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.min_salary' => ['required', 'numeric', 'min:0'],
            'tiers.*.max_salary' => ['nullable', 'numeric', 'min:0', 'gt:tiers.*.min_salary'],
            'tiers.*.employee_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'tiers.*.employer_rate' => ['required', 'numeric', 'min:0', 'max:1'],
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
            'max_monthly_compensation.required' => 'The maximum monthly compensation is required.',
            'tiers.required' => 'At least one contribution tier is required.',
            'tiers.*.min_salary.required' => 'The minimum salary is required for each tier.',
            'tiers.*.employee_rate.required' => 'The employee rate is required for each tier.',
            'tiers.*.employer_rate.required' => 'The employer rate is required for each tier.',
        ];
    }
}
