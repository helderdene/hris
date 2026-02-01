<?php

namespace App\Http\Requests;

use App\Enums\LoanType;
use App\Models\EmployeeLoan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateEmployeeLoanRequest extends FormRequest
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
        $loan = $this->route('loan');
        $loanId = $loan instanceof EmployeeLoan ? $loan->id : $loan;

        return [
            'loan_type' => ['sometimes', new Enum(LoanType::class)],
            'loan_code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique(EmployeeLoan::class)->where(function ($query) use ($loan) {
                    return $query->where('employee_id', $loan->employee_id);
                })->ignore($loanId),
            ],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'principal_amount' => ['sometimes', 'numeric', 'min:1', 'max:99999999.99'],
            'interest_rate' => ['sometimes', 'numeric', 'min:0', 'max:1'],
            'monthly_deduction' => ['sometimes', 'numeric', 'min:1', 'max:99999999.99'],
            'term_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'total_amount' => ['sometimes', 'numeric', 'min:1', 'max:99999999.99'],
            'start_date' => ['sometimes', 'date'],
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
            'loan_code.unique' => 'This employee already has a loan with this code.',
            'principal_amount.min' => 'The principal amount must be at least 1.',
            'monthly_deduction.min' => 'The monthly deduction must be at least 1.',
            'expected_end_date.after_or_equal' => 'The expected end date must be after or equal to the start date.',
        ];
    }
}
