<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateKioskRequest extends FormRequest
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
            'work_location_id' => ['required', 'integer', 'exists:tenant.work_locations,id'],
            'location' => ['nullable', 'string', 'max:500'],
            'ip_whitelist' => ['nullable', 'array'],
            'ip_whitelist.*' => ['string', 'max:45'],
            'settings' => ['nullable', 'array'],
            'settings.cooldown_minutes' => ['nullable', 'integer', 'min:1', 'max:60'],
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
            'name.required' => 'The kiosk name is required.',
            'work_location_id.required' => 'A work location must be selected.',
            'work_location_id.exists' => 'The selected work location does not exist.',
        ];
    }
}
