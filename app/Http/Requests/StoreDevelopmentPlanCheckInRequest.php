<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDevelopmentPlanCheckInRequest extends FormRequest
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
            'check_in_date' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['required', 'string', 'min:10'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_in_date.required' => 'Please select a check-in date.',
            'check_in_date.before_or_equal' => 'Check-in date cannot be in the future.',
            'notes.required' => 'Please provide notes from the discussion.',
            'notes.min' => 'Please provide more detailed notes (at least 10 characters).',
        ];
    }
}
