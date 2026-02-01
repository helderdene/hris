<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CopyHolidaysRequest extends FormRequest
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
        $currentYear = now()->year;
        $maxYear = $currentYear + 5;

        return [
            'target_year' => [
                'required',
                'integer',
                "min:{$currentYear}",
                "max:{$maxYear}",
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $currentYear = now()->year;
        $maxYear = $currentYear + 5;

        return [
            'target_year.required' => 'The target year is required.',
            'target_year.integer' => 'The target year must be a valid year.',
            'target_year.min' => "The target year must be at least {$currentYear}.",
            'target_year.max' => "The target year cannot be more than {$maxYear}.",
        ];
    }
}
