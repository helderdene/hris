<?php

namespace App\Http\Requests;

use App\Enums\BankAccountType;
use App\Enums\PayType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeCompensationRequest extends FormRequest
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
            'basic_pay' => ['required', 'numeric', 'min:0'],
            'pay_type' => ['required', 'string', Rule::in(PayType::values())],
            'effective_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:100'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'account_type' => ['nullable', 'string', Rule::in(BankAccountType::values())],
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
            'basic_pay.required' => 'The basic pay amount is required.',
            'basic_pay.numeric' => 'The basic pay must be a valid number.',
            'basic_pay.min' => 'The basic pay cannot be negative.',
            'pay_type.required' => 'The pay type is required.',
            'pay_type.in' => 'The selected pay type is invalid. Valid types are: monthly, semi_monthly, weekly, daily.',
            'effective_date.required' => 'The effective date is required.',
            'effective_date.date' => 'Please enter a valid effective date.',
            'remarks.max' => 'The remarks may not exceed 1000 characters.',
            'bank_name.max' => 'The bank name may not exceed 100 characters.',
            'account_name.max' => 'The account name may not exceed 100 characters.',
            'account_number.max' => 'The account number may not exceed 50 characters.',
            'account_type.in' => 'The selected account type is invalid. Valid types are: savings, checking.',
        ];
    }
}
