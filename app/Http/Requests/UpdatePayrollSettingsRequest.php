<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authorization is handled by checking if the user is an admin in the current tenant.
     */
    public function authorize(): bool
    {
        $tenant = tenant();
        $user = $this->user();

        if (! $tenant || ! $user) {
            return false;
        }

        return $user->isAdminInTenant($tenant);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'double_holiday_rate' => ['sometimes', 'integer', 'min:100', 'max:500'],
            'pay_frequency' => ['sometimes', 'string', 'in:weekly,semi-monthly,monthly'],
            'cutoff_day' => ['sometimes', 'integer', 'min:1', 'max:31'],
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
            'double_holiday_rate.integer' => 'The double holiday rate must be a whole number.',
            'double_holiday_rate.min' => 'The double holiday rate must be at least 100%.',
            'double_holiday_rate.max' => 'The double holiday rate must not exceed 500%.',
            'pay_frequency.in' => 'The pay frequency must be weekly, semi-monthly, or monthly.',
            'cutoff_day.min' => 'The cutoff day must be at least 1.',
            'cutoff_day.max' => 'The cutoff day must not exceed 31.',
        ];
    }
}
