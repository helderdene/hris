<?php

namespace App\Http\Requests;

use App\Enums\LoanApplicationStatus;
use App\Models\LoanApplication;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates approve actions on a loan application.
 *
 * Levels 1 and 2 (CFO, Admin Manager) only require optional remarks.
 * Level 3 (Releasing officer) requires interest_rate and start_date because
 * that's when the EmployeeLoan is created.
 */
class ApproveLoanApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('loan_application');

        return $application && $application->status === LoanApplicationStatus::Pending;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isFinalLevel = $this->isFinalLevel();

        return [
            'interest_rate' => [$isFinalLevel ? 'required' : 'nullable', 'numeric', 'min:0', 'max:1'],
            'start_date' => [$isFinalLevel ? 'required' : 'nullable', 'date', 'after_or_equal:today'],
            'remarks' => ['nullable', 'string', 'max:1000'],
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
            'interest_rate.required' => 'Please enter the interest rate.',
            'interest_rate.min' => 'Interest rate cannot be negative.',
            'interest_rate.max' => 'Interest rate cannot exceed 100%.',
            'start_date.required' => 'Please select a start date.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'remarks.max' => 'Remarks cannot exceed 1000 characters.',
        ];
    }

    /**
     * Whether the current approval step is the last in the chain (Releasing).
     */
    protected function isFinalLevel(): bool
    {
        $application = $this->route('loan_application');

        if (! $application instanceof LoanApplication) {
            return false;
        }

        return $application->current_approval_level >= max(1, (int) $application->total_approval_levels);
    }
}
