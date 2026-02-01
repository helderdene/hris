<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for processing year-end leave balances.
 */
class ProcessYearEndRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in the controller
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $currentYear = now()->year;

        return [
            'year' => [
                'required',
                'integer',
                'min:'.($currentYear - 5),
                'max:'.$currentYear,
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
        return [
            'year.required' => 'Please specify the year to process.',
            'year.integer' => 'Year must be a valid number.',
            'year.min' => 'Year cannot be more than 5 years in the past.',
            'year.max' => 'Cannot process year-end for future years.',
        ];
    }
}
