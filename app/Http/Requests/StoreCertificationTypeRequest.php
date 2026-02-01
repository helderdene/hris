<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCertificationTypeRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'validity_period_months' => ['nullable', 'integer', 'min:1', 'max:600'],
            'reminder_days_before_expiry' => ['nullable', 'array'],
            'reminder_days_before_expiry.*' => ['integer', 'min:1', 'max:365'],
            'is_mandatory' => ['boolean'],
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
            'name.required' => 'The certification type name is required.',
            'name.max' => 'The certification type name must not exceed 255 characters.',
            'validity_period_months.min' => 'The validity period must be at least 1 month.',
            'validity_period_months.max' => 'The validity period must not exceed 600 months (50 years).',
            'reminder_days_before_expiry.*.min' => 'Reminder days must be at least 1.',
            'reminder_days_before_expiry.*.max' => 'Reminder days must not exceed 365.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $defaults = [
            'is_mandatory' => false,
            'is_active' => true,
        ];

        foreach ($defaults as $key => $value) {
            if (! $this->has($key)) {
                $this->merge([$key => $value]);
            }
        }
    }
}
