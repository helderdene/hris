<?php

namespace App\Http\Requests;

use App\Models\WithholdingTaxTable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWithholdingTaxTableRequest extends FormRequest
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
            'pay_period' => ['required', 'string', Rule::in(WithholdingTaxTable::PAY_PERIODS)],
            'effective_from' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'brackets' => ['required', 'array', 'min:1'],
            'brackets.*.min_compensation' => ['required', 'numeric', 'min:0'],
            'brackets.*.max_compensation' => ['nullable', 'numeric', 'min:0', 'gt:brackets.*.min_compensation'],
            'brackets.*.base_tax' => ['required', 'numeric', 'min:0'],
            'brackets.*.excess_rate' => ['required', 'numeric', 'min:0', 'max:1'],
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
            'pay_period.required' => 'The pay period is required.',
            'pay_period.in' => 'The pay period must be one of: daily, weekly, semi_monthly, monthly.',
            'effective_from.required' => 'The effective date is required.',
            'brackets.required' => 'At least one tax bracket is required.',
            'brackets.min' => 'At least one tax bracket is required.',
            'brackets.*.min_compensation.required' => 'The minimum compensation is required for each bracket.',
            'brackets.*.min_compensation.min' => 'The minimum compensation must be at least 0.',
            'brackets.*.max_compensation.gt' => 'The maximum compensation must be greater than the minimum compensation.',
            'brackets.*.base_tax.required' => 'The base tax is required for each bracket.',
            'brackets.*.base_tax.min' => 'The base tax must be at least 0.',
            'brackets.*.excess_rate.required' => 'The excess rate is required for each bracket.',
            'brackets.*.excess_rate.min' => 'The excess rate must be at least 0.',
            'brackets.*.excess_rate.max' => 'The excess rate must not exceed 1 (100%).',
        ];
    }
}
