<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePhilhealthContributionTableRequest extends FormRequest
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
            'contribution_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'employee_share_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'employer_share_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'salary_floor' => ['required', 'numeric', 'min:0'],
            'salary_ceiling' => ['required', 'numeric', 'min:0', 'gt:salary_floor'],
            'min_contribution' => ['nullable', 'numeric', 'min:0'],
            'max_contribution' => ['nullable', 'numeric', 'min:0', 'gte:min_contribution'],
            'is_active' => ['boolean'],
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
            'contribution_rate.required' => 'The contribution rate is required.',
            'salary_floor.required' => 'The salary floor is required.',
            'salary_ceiling.required' => 'The salary ceiling is required.',
            'salary_ceiling.gt' => 'The salary ceiling must be greater than the salary floor.',
            'max_contribution.gte' => 'The maximum contribution must be greater than or equal to the minimum contribution.',
        ];
    }
}
