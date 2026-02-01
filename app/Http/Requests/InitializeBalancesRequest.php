<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form request for initializing leave balances.
 */
class InitializeBalancesRequest extends FormRequest
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
                'max:'.($currentYear + 1),
            ],
            'employee_id' => [
                'nullable',
                'integer',
                'exists:employees,id',
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

        return [
            'year.required' => 'Please specify the year.',
            'year.integer' => 'Year must be a valid number.',
            'year.min' => 'Year cannot be more than 5 years in the past.',
            'year.max' => 'Year cannot be more than 1 year in the future.',
            'employee_id.exists' => 'The selected employee does not exist.',
        ];
    }
}
