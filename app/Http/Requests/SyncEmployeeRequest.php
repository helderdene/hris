<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for syncing an employee to biometric devices.
 */
class SyncEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by Gate in controller
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_ids' => ['nullable', 'array'],
            'device_ids.*' => ['integer', 'exists:tenant.biometric_devices,id'],
            'immediate' => ['nullable', 'boolean'],
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
            'device_ids.array' => 'Device IDs must be provided as an array.',
            'device_ids.*.integer' => 'Each device ID must be an integer.',
            'device_ids.*.exists' => 'One or more device IDs are invalid.',
        ];
    }
}
