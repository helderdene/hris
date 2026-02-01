<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateComplianceCourseRequest extends FormRequest
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
            'days_to_complete' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'validity_months' => ['nullable', 'integer', 'min:1', 'max:120'],
            'passing_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'allow_retakes_after_pass' => ['boolean'],
            'requires_acknowledgment' => ['boolean'],
            'acknowledgment_text' => ['nullable', 'string', 'max:5000'],
            'reminder_days' => ['nullable', 'array'],
            'reminder_days.*' => ['integer', 'min:1', 'max:365'],
            'escalation_days' => ['nullable', 'array'],
            'escalation_days.*' => ['integer', 'min:1', 'max:365'],
            'auto_reassign_on_expiry' => ['boolean'],
            'completion_message' => ['nullable', 'string', 'max:2000'],
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
            'days_to_complete.min' => 'Days to complete must be at least 1.',
            'passing_score.min' => 'Passing score must be at least 0.',
            'passing_score.max' => 'Passing score cannot exceed 100.',
            'max_attempts.min' => 'Maximum attempts must be at least 1.',
        ];
    }
}
