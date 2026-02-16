<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ComputePayrollRequest extends FormRequest
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
            'employee_ids' => ['nullable', 'array'],
            'employee_ids.*' => ['integer', 'exists:tenant.employees,id'],
            'force_recompute' => ['nullable', 'boolean'],
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
            'employee_ids.array' => 'Employee IDs must be provided as an array.',
            'employee_ids.*.integer' => 'Each employee ID must be an integer.',
            'employee_ids.*.exists' => 'One or more employee IDs do not exist.',
        ];
    }
}
