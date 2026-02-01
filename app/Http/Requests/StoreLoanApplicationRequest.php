<?php

namespace App\Http\Requests;

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
            'term_months' => ['required', 'integer', 'min:1', 'max:360'],
            'purpose' => ['nullable', 'string', 'max:2000'],
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
            'term_months.required' => 'Please enter the loan term.',
            'term_months.min' => 'Loan term must be at least 1 month.',
            'term_months.max' => 'Loan term cannot exceed 360 months.',
            'documents.*.max' => 'Each document must not exceed 10MB.',
        ];
    }
}
