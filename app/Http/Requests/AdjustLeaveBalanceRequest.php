<?php

namespace App\Http\Requests;

use App\Enums\LeaveBalanceAdjustmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form request for adjusting a leave balance.
 */
class AdjustLeaveBalanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'adjustment_type' => [
                'required',
                'string',
                Rule::in(LeaveBalanceAdjustmentType::values()),
            ],
            'days' => [
                'required',
                'numeric',
                'gt:0',
                'max:999.99',
            ],
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:1000',
            ],
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
            'adjustment_type.required' => 'Please select an adjustment type.',
            'adjustment_type.in' => 'Invalid adjustment type. Must be credit or debit.',
            'days.required' => 'Please enter the number of days.',
            'days.gt' => 'Days must be greater than zero.',
            'days.max' => 'Days cannot exceed 999.99.',
            'reason.required' => 'Please provide a reason for this adjustment.',
            'reason.min' => 'Reason must be at least 10 characters.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
        ];
    }
}
