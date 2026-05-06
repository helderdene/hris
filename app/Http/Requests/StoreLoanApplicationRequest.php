<?php

namespace App\Http\Requests;

use App\Enums\LoanDeductionSchedule;
use App\Enums\LoanType;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanApplicationRequest extends FormRequest
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
            'loan_type' => ['required', 'string', Rule::in(LoanType::values())],
            'amount_requested' => ['required', 'numeric', 'min:1'],
            'term_months' => ['required', 'integer', Rule::in([3, 6, 12, 24, 36])],
            'deduction_schedule' => ['required', 'string', Rule::in(LoanDeductionSchedule::values())],
            'urgency_level' => ['required', 'integer', 'between:1,5'],
            'purpose' => ['required', 'string', 'max:2000'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['file', 'max:10240'],
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
            'loan_type.in' => 'The selected loan type is invalid.',
            'amount_requested.required' => 'Please enter the loan amount.',
            'amount_requested.min' => 'Loan amount must be at least 1.',
            'term_months.required' => 'Please select a preferred repayment term.',
            'term_months.in' => 'Preferred repayment must be 3, 6, 12, 24, or 36 months.',
            'deduction_schedule.required' => 'Please select a preferred deduction schedule.',
            'deduction_schedule.in' => 'The selected deduction schedule is invalid.',
            'urgency_level.required' => 'Please indicate the level of urgency.',
            'urgency_level.between' => 'Urgency level must be between 1 (low) and 5 (high).',
            'purpose.required' => 'Please describe the purpose of this loan.',
            'documents.*.max' => 'Each document must not exceed 10MB.',
        ];
    }
}
