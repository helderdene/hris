<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartOffboardingRequest extends FormRequest
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
            'separation_type' => ['required', 'string', Rule::in(array_diff(EmploymentStatus::values(), [EmploymentStatus::Active->value]))],
            'filed_date' => ['nullable', 'date'],
            'last_working_date' => ['required', 'date'],
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
            'separation_type.required' => 'The separation type is required.',
            'separation_type.in' => 'The selected separation type is invalid.',
            'last_working_date.required' => 'The last working day is required.',
            'last_working_date.date' => 'Please enter a valid last working day.',
            'reason.max' => 'The reason may not exceed 500 characters.',
        ];
    }
}
