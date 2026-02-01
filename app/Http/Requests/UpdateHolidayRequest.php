<?php

namespace App\Http\Requests;

use App\Enums\HolidayType;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateHolidayRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'date' => ['sometimes', 'required', 'date'],
            'holiday_type' => ['sometimes', 'required', new Enum(HolidayType::class)],
            'description' => ['nullable', 'string'],
            'is_national' => ['sometimes', 'boolean'],
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
}
