<?php

namespace App\Http\Requests;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Models\Employee;
use App\Models\EmployeeLoan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEmployeeLoanRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'loan_type' => ['required', new Enum(LoanType::class)],
            'loan_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(EmployeeLoan::class)->where(function ($query) {
                    return $query->where('employee_id', $this->input('employee_id'));
                }),
            ],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'principal_amount' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'interest_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'monthly_deduction' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'total_amount' => ['required', 'numeric', 'min:1', 'max:99999999.99'],
            'start_date' => ['required', 'date'],
            'expected_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'metadata' => ['nullable', 'array'],
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
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'loan_type.required' => 'Please select a loan type.',
            'loan_code.required' => 'The loan code is required.',
            'loan_code.unique' => 'This employee already has a loan with this code.',
            'principal_amount.required' => 'The principal amount is required.',
            'principal_amount.min' => 'The principal amount must be at least 1.',
            'monthly_deduction.required' => 'The monthly deduction amount is required.',
            'monthly_deduction.min' => 'The monthly deduction must be at least 1.',
            'total_amount.required' => 'The total loan amount is required.',
            'start_date.required' => 'The loan start date is required.',
            'expected_end_date.after_or_equal' => 'The expected end date must be after or equal to the start date.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('interest_rate')) {
            $this->merge(['interest_rate' => 0]);
        }
    }

    /**
     * Get the validated data with computed fields.
     *
     * @return array<string, mixed>
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        $validated['remaining_balance'] = $validated['total_amount'];
        $validated['total_paid'] = 0;
        $validated['status'] = LoanStatus::Active->value;
        $validated['created_by'] = auth()->id();

        return $validated;
    }
}
