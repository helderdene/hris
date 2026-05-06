<?php

namespace App\Http\Requests;

use App\Enums\LoanApplicationStatus;
use App\Enums\LoanDeductionSchedule;
use App\Enums\LoanType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('loan_application');

        return $application && $application->status === LoanApplicationStatus::Draft;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'loan_type' => ['sometimes', 'string', Rule::in(LoanType::values())],
            'amount_requested' => ['sometimes', 'numeric', 'min:1'],
            'term_months' => ['sometimes', 'integer', Rule::in([3, 6, 12, 24, 36])],
            'deduction_schedule' => ['sometimes', 'string', Rule::in(LoanDeductionSchedule::values())],
            'urgency_level' => ['sometimes', 'integer', 'between:1,5'],
            'purpose' => ['sometimes', 'required', 'string', 'max:2000'],
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
            'loan_type.in' => 'The selected loan type is invalid.',
            'amount_requested.min' => 'Loan amount must be at least 1.',
            'term_months.in' => 'Preferred repayment must be 3, 6, 12, 24, or 36 months.',
            'deduction_schedule.in' => 'The selected deduction schedule is invalid.',
            'urgency_level.between' => 'Urgency level must be between 1 (low) and 5 (high).',
            'documents.*.max' => 'Each document must not exceed 10MB.',
        ];
    }
}
