<?php

namespace App\Http\Requests;

use App\Models\EmployeeScheduleAssignment;
use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeScheduleAssignmentRequest extends FormRequest
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
            'shift_name' => ['nullable', 'string', 'max:100'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty()) {
                $this->validateEndDateAfterEffectiveDate($validator);
            }
        });
    }

    /**
     * Validate that end_date is after effective_date of the assignment.
     */
    protected function validateEndDateAfterEffectiveDate(\Illuminate\Validation\Validator $validator): void
    {
        $endDate = $this->input('end_date');

        if ($endDate === null) {
            return;
        }

        $assignment = $this->route('assignment');
        if ($assignment instanceof EmployeeScheduleAssignment) {
            $effectiveDate = $assignment->effective_date?->toDateString();

            if ($effectiveDate && $endDate < $effectiveDate) {
                $validator->errors()->add('end_date', 'The end date must be on or after the effective date.');
            }
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.date_format' => 'The end date must be in YYYY-MM-DD format.',
        ];
    }
}
