<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompleteOnboardingItemRequest extends FormRequest
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
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:2000'],
            'equipment_details' => ['nullable', 'array'],
            'equipment_details.model' => ['nullable', 'string', 'max:255'],
            'equipment_details.serial_number' => ['nullable', 'string', 'max:255'],
            'equipment_details.asset_tag' => ['nullable', 'string', 'max:255'],
            'equipment_details.assigned_date' => ['nullable', 'date'],
        ];
    }
}
