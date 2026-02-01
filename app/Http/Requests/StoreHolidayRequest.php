<?php

namespace App\Http\Requests;

use App\Enums\HolidayType;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreHolidayRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'holiday_type' => ['required', new Enum(HolidayType::class)],
            'description' => ['nullable', 'string'],
            'is_national' => ['boolean'],
            'work_location_id' => ['nullable', Rule::exists(WorkLocation::class, 'id')],
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
            'name.required' => 'The holiday name is required.',
            'name.max' => 'The holiday name must not exceed 255 characters.',
            'date.required' => 'The holiday date is required.',
            'date.date' => 'The holiday date must be a valid date.',
            'holiday_type.required' => 'The holiday type is required.',
            'holiday_type.Illuminate\Validation\Rules\Enum' => 'The selected holiday type is invalid.',
            'work_location_id.exists' => 'The selected work location does not exist.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Default is_national to true if not provided
        if (! $this->has('is_national')) {
            $this->merge(['is_national' => true]);
        }
    }
}
