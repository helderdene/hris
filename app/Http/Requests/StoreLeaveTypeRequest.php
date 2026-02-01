<?php

namespace App\Http\Requests;

use App\Enums\AccrualMethod;
use App\Enums\GenderRestriction;
use App\Enums\LeaveCategory;
use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreLeaveTypeRequest extends FormRequest
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
            // Basic Information
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique(LeaveType::class, 'code')],
            'description' => ['nullable', 'string'],
            'leave_category' => ['required', new Enum(LeaveCategory::class)],

            // Accrual Configuration
            'accrual_method' => ['required', new Enum(AccrualMethod::class)],
            'default_days_per_year' => ['required', 'numeric', 'min:0', 'max:365'],
            'monthly_accrual_rate' => ['nullable', 'numeric', 'min:0', 'max:31'],
            'tenure_brackets' => ['nullable', 'array'],
            'tenure_brackets.*.years' => ['required_with:tenure_brackets', 'integer', 'min:0'],
            'tenure_brackets.*.days' => ['required_with:tenure_brackets', 'numeric', 'min:0'],

            // Carry-over Settings
            'allow_carry_over' => ['boolean'],
            'max_carry_over_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'carry_over_expiry_months' => ['nullable', 'integer', 'min:1', 'max:24'],

            // Cash Conversion Settings
            'is_convertible_to_cash' => ['boolean'],
            'cash_conversion_rate' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'max_convertible_days' => ['nullable', 'numeric', 'min:0', 'max:365'],

            // Eligibility Rules
            'min_tenure_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'eligible_employment_types' => ['nullable', 'array'],
            'eligible_employment_types.*' => ['string'],
            'gender_restriction' => ['nullable', new Enum(GenderRestriction::class)],

            // Additional Settings
            'requires_attachment' => ['boolean'],
            'requires_approval' => ['boolean'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'min_days_advance_notice' => ['nullable', 'integer', 'min:0', 'max:90'],

            // Statutory Tracking
            'is_statutory' => ['boolean'],
            'statutory_reference' => ['nullable', 'string', 'max:100'],

            // Status
            'is_active' => ['boolean'],
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
            'name.required' => 'The leave type name is required.',
            'name.max' => 'The leave type name must not exceed 255 characters.',
            'code.required' => 'The leave type code is required.',
            'code.max' => 'The leave type code must not exceed 50 characters.',
            'code.unique' => 'This leave type code is already in use.',
            'leave_category.required' => 'The leave category is required.',
            'leave_category.Illuminate\Validation\Rules\Enum' => 'The selected leave category is invalid.',
            'accrual_method.required' => 'The accrual method is required.',
            'accrual_method.Illuminate\Validation\Rules\Enum' => 'The selected accrual method is invalid.',
            'default_days_per_year.required' => 'The default days per year is required.',
            'default_days_per_year.min' => 'The default days per year must be at least 0.',
            'default_days_per_year.max' => 'The default days per year must not exceed 365.',
            'gender_restriction.Illuminate\Validation\Rules\Enum' => 'The selected gender restriction is invalid.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default values if not provided
        $defaults = [
            'allow_carry_over' => false,
            'is_convertible_to_cash' => false,
            'requires_attachment' => false,
            'requires_approval' => true,
            'is_statutory' => false,
            'is_active' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (! $this->has($key)) {
                $this->merge([$key => $value]);
            }
        }
    }
}
