<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Enums\OffboardingStepStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOffboardingRequest extends FormRequest
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
            'separation_type' => ['sometimes', 'required', 'string', Rule::in(array_diff(EmploymentStatus::values(), [EmploymentStatus::Active->value]))],
            'filed_date' => ['sometimes', 'nullable', 'date'],
            'last_working_date' => ['sometimes', 'required', 'date'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'exit_interview_date' => ['sometimes', 'nullable', 'date'],
            'exit_interviewer' => ['sometimes', 'nullable', 'string', 'max:255'],
            'exit_interview_notes' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'final_pay_status' => ['sometimes', 'required', Rule::enum(OffboardingStepStatus::class)],
            'final_pay_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'final_pay_released_date' => ['sometimes', 'nullable', 'date'],
            'coe_status' => ['sometimes', 'required', Rule::enum(OffboardingStepStatus::class)],
            'coe_issued_date' => ['sometimes', 'nullable', 'date'],
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
            'separation_type.in' => 'The selected separation type is invalid.',
            'last_working_date.required' => 'The last working day is required.',
            'final_pay_amount.numeric' => 'The final pay amount must be a number.',
            'final_pay_amount.min' => 'The final pay amount cannot be negative.',
            'exit_interview_notes.max' => 'The exit interview notes may not exceed 10000 characters.',
        ];
    }
}
