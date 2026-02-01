<?php

namespace App\Http\Requests;

use App\Models\EmployeeLoan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordLoanPaymentRequest extends FormRequest
{
    /**
     * The loan being paid (for testing).
     */
    protected ?EmployeeLoan $loan = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Set the loan for validation (used in tests).
     */
    public function setLoan(EmployeeLoan $loan): static
    {
        $this->loan = $loan;

        return $this;
    }

    /**
     * Get the loan being paid.
     */
    protected function getLoan(): ?EmployeeLoan
    {
        return $this->loan ?? $this->route('loan');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $loan = $this->getLoan();
        $maxAmount = $loan instanceof EmployeeLoan ? (float) $loan->remaining_balance : 99999999.99;

        return [
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$maxAmount],
            'payment_date' => ['required', 'date'],
            'payment_source' => ['sometimes', 'string', Rule::in(['manual', 'adjustment'])],
            'notes' => ['nullable', 'string', 'max:500'],
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
            'amount.required' => 'The payment amount is required.',
            'amount.min' => 'The payment amount must be at least 0.01.',
            'amount.max' => 'The payment amount cannot exceed the remaining balance.',
            'payment_date.required' => 'The payment date is required.',
            'payment_source.in' => 'Invalid payment source.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('payment_source')) {
            $this->merge(['payment_source' => 'manual']);
        }
    }
}
