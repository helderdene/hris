<?php

namespace App\Http\Requests;

use App\Models\PayrollCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePayrollPeriodsRequest extends FormRequest
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
            'payroll_cycle_id' => ['required', Rule::exists(PayrollCycle::class, 'id')],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'overwrite_existing' => ['boolean'],
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
            'payroll_cycle_id.required' => 'A payroll cycle must be selected.',
            'payroll_cycle_id.exists' => 'The selected payroll cycle does not exist.',
            'year.required' => 'The year is required.',
            'year.integer' => 'The year must be a valid number.',
            'year.min' => 'The year must be 2000 or later.',
            'year.max' => 'The year must be 2100 or earlier.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('overwrite_existing')) {
            $this->merge(['overwrite_existing' => false]);
        }
    }
}
