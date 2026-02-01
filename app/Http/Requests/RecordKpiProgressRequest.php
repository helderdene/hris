<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordKpiProgressRequest extends FormRequest
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
            'value' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'value.required' => 'The progress value is required.',
            'value.numeric' => 'The progress value must be a number.',
            'value.min' => 'The progress value must be a positive number.',
            'notes.max' => 'Notes must not exceed 1000 characters.',
        ];
    }
}
