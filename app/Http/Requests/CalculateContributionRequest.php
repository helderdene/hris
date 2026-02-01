<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculateContributionRequest extends FormRequest
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
            'salary' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['nullable', 'date'],
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
            'salary.required' => 'The salary amount is required.',
            'salary.numeric' => 'The salary must be a number.',
            'salary.min' => 'The salary must be at least 0.',
            'effective_date.date' => 'The effective date must be a valid date.',
        ];
    }
}
