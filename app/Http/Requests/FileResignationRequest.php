<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FileResignationRequest extends FormRequest
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
            'last_working_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:500'],
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
            'last_working_date.required' => 'Your intended last working day is required.',
            'last_working_date.date' => 'Please enter a valid last working day.',
            'last_working_date.after_or_equal' => 'Your last working day cannot be in the past.',
            'reason.max' => 'The reason may not exceed 500 characters.',
        ];
    }
}
