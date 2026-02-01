<?php

namespace App\Http\Requests;

use App\Enums\AdjustmentFrequency;
use App\Enums\AdjustmentType;
use App\Enums\RecurringInterval;
use App\Models\EmployeeAdjustment;
use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateEmployeeAdjustmentRequest extends FormRequest
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
        $adjustment = $this->route('adjustment');
        $adjustmentId = $adjustment instanceof EmployeeAdjustment ? $adjustment->id : $adjustment;

        return [
            'adjustment_type' => ['sometimes', new Enum(AdjustmentType::class)],
            'adjustment_code' => [
                'sometimes',
                'string',
                'max:50',
                Rule::unique(EmployeeAdjustment::class)
                    ->where(function ($query) use ($adjustment) {
                        $employeeId = $adjustment instanceof EmployeeAdjustment
                            ? $adjustment->employee_id
                            : $this->input('employee_id');

                        return $query->where('employee_id', $employeeId);
                    })
                    ->ignore($adjustmentId),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['sometimes', 'numeric', 'min:0.01', 'max:99999999.99'],
            'is_taxable' => ['sometimes', 'boolean'],
            'frequency' => ['sometimes', new Enum(AdjustmentFrequency::class)],

            // Recurring fields
            'recurring_start_date' => ['nullable', 'date'],
            'recurring_end_date' => ['nullable', 'date', 'after_or_equal:recurring_start_date'],
            'recurring_interval' => ['nullable', new Enum(RecurringInterval::class)],
            'remaining_occurrences' => ['nullable', 'integer', 'min:0', 'max:999'],

            // One-time adjustment target period
            'target_payroll_period_id' => [
                'nullable',
                'integer',
                Rule::exists(PayrollPeriod::class, 'id'),
            ],

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
            'adjustment_code.unique' => 'This employee already has an adjustment with this code.',
            'amount.min' => 'The amount must be at least 0.01.',
            'recurring_end_date.after_or_equal' => 'The end date must be after or equal to the start date.',
        ];
    }
}
