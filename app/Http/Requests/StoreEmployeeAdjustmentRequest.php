<?php

namespace App\Http\Requests;

use App\Enums\AdjustmentCategory;
use App\Enums\AdjustmentFrequency;
use App\Enums\AdjustmentStatus;
use App\Enums\AdjustmentType;
use App\Enums\RecurringInterval;
use App\Models\Employee;
use App\Models\EmployeeAdjustment;
use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreEmployeeAdjustmentRequest extends FormRequest
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
            'adjustment_type' => ['required', new Enum(AdjustmentType::class)],
            'adjustment_code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(EmployeeAdjustment::class)->where(function ($query) {
                    return $query->where('employee_id', $this->input('employee_id'));
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'is_taxable' => ['boolean'],
            'frequency' => ['required', new Enum(AdjustmentFrequency::class)],

            // Recurring fields
            'recurring_start_date' => ['nullable', 'date', 'required_if:frequency,recurring'],
            'recurring_end_date' => ['nullable', 'date', 'after_or_equal:recurring_start_date'],
            'recurring_interval' => [
                'nullable',
                new Enum(RecurringInterval::class),
                'required_if:frequency,recurring',
            ],
            'remaining_occurrences' => ['nullable', 'integer', 'min:1', 'max:999'],

            // Balance tracking (for loan-type adjustments)
            'has_balance_tracking' => ['boolean'],
            'total_amount' => [
                'nullable',
                'numeric',
                'min:0.01',
                'max:99999999.99',
                'required_if:has_balance_tracking,true',
            ],

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
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'adjustment_type.required' => 'Please select an adjustment type.',
            'adjustment_code.required' => 'The adjustment code is required.',
            'adjustment_code.unique' => 'This employee already has an adjustment with this code.',
            'name.required' => 'The adjustment name is required.',
            'amount.required' => 'The adjustment amount is required.',
            'amount.min' => 'The amount must be at least 0.01.',
            'frequency.required' => 'Please select a frequency.',
            'recurring_start_date.required_if' => 'Start date is required for recurring adjustments.',
            'recurring_interval.required_if' => 'Interval is required for recurring adjustments.',
            'total_amount.required_if' => 'Total amount is required when balance tracking is enabled.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Default is_taxable based on adjustment type
        if (! $this->has('is_taxable') && $this->has('adjustment_type')) {
            $type = AdjustmentType::tryFrom($this->input('adjustment_type'));
            $this->merge([
                'is_taxable' => $type ? $type->category() === AdjustmentCategory::Earning : true,
            ]);
        }

        // Default has_balance_tracking based on adjustment type
        if (! $this->has('has_balance_tracking') && $this->has('adjustment_type')) {
            $type = AdjustmentType::tryFrom($this->input('adjustment_type'));
            $this->merge([
                'has_balance_tracking' => $type ? $type->supportsBalanceTracking() : false,
            ]);
        }
    }

    /**
     * Get the validated data with computed fields.
     *
     * @return array<string, mixed>
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();

        $adjustmentType = AdjustmentType::from($validated['adjustment_type']);

        // Set category from type
        $validated['adjustment_category'] = $adjustmentType->category()->value;

        // Set status
        $validated['status'] = AdjustmentStatus::Active->value;

        // Set created_by
        $validated['created_by'] = auth()->id();

        // Initialize balance tracking fields
        if ($validated['has_balance_tracking'] ?? false) {
            $validated['total_applied'] = 0;
            $validated['remaining_balance'] = $validated['total_amount'];
        }

        return $validated;
    }
}
