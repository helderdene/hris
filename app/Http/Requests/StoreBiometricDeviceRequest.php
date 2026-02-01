<?php

namespace App\Http\Requests;

use App\Models\BiometricDevice;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBiometricDeviceRequest extends FormRequest
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
            'device_identifier' => [
                'required',
                'string',
                'max:255',
                Rule::unique(BiometricDevice::class, 'device_identifier'),
            ],
            'work_location_id' => [
                'required',
                'integer',
                Rule::exists(WorkLocation::class, 'id')->where('status', 'active'),
            ],
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
            'name.required' => 'The device name is required.',
            'name.max' => 'The device name cannot exceed 255 characters.',
            'device_identifier.required' => 'The device identifier is required.',
            'device_identifier.unique' => 'This device identifier is already in use.',
            'device_identifier.max' => 'The device identifier cannot exceed 255 characters.',
            'work_location_id.required' => 'A work location must be selected.',
            'work_location_id.exists' => 'The selected work location is invalid or inactive.',
        ];
    }
}
