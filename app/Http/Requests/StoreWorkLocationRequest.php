<?php

namespace App\Http\Requests;

use App\Enums\LocationType;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreWorkLocationRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:50', Rule::unique(WorkLocation::class, 'code')],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'location_type' => ['required', new Enum(LocationType::class)],
            'timezone' => ['nullable', 'string', 'max:100'],
            'metadata' => ['nullable', 'array'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius' => ['nullable', 'integer', 'min:10', 'max:50000'],
            'ip_whitelist' => ['nullable', 'array'],
            'ip_whitelist.*' => ['string', 'max:45'],
            'location_check' => ['nullable', 'string', Rule::in(['none', 'ip', 'gps', 'both', 'any'])],
            'self_service_clockin_enabled' => ['boolean'],
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
            'name.required' => 'The location name is required.',
            'code.required' => 'The location code is required.',
            'code.unique' => 'This location code is already in use.',
            'location_type.required' => 'The location type is required.',
            'location_type.Illuminate\Validation\Rules\Enum' => 'The selected location type is invalid.',
            'status.required' => 'The location status is required.',
            'status.in' => 'The status must be either active or inactive.',
            'metadata.array' => 'The metadata must be a valid array.',
        ];
    }
}
