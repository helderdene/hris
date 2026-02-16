<?php

namespace App\Http\Requests;

use App\Enums\OvertimeType;
use App\Models\OvertimeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOvertimeRequestRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:tenant.employees,id'],
            'overtime_date' => ['required', 'date'],
            'expected_start_time' => ['nullable', 'date_format:H:i'],
            'expected_end_time' => ['nullable', 'date_format:H:i'],
            'expected_minutes' => ['required', 'integer', 'min:30', 'max:720'],
            'overtime_type' => ['required', Rule::in(OvertimeType::values())],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $validator->errors()->has('employee_id') && ! $validator->errors()->has('overtime_date')) {
                $this->validateNoOverlap($validator);
            }
        });
    }

    /**
     * Validate no overlapping overtime requests for the same date.
     */
    protected function validateNoOverlap($validator): void
    {
        $employeeId = $this->input('employee_id');
        $overtimeDate = $this->input('overtime_date');

        $exists = OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->where('overtime_date', $overtimeDate)
            ->whereIn('status', ['draft', 'pending', 'approved'])
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                'overtime_date',
                'An overtime request already exists for this date.'
            );
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
            'expected_minutes.min' => 'Overtime must be at least 30 minutes.',
            'expected_minutes.max' => 'Overtime cannot exceed 12 hours (720 minutes).',
            'reason.required' => 'Please provide a reason for the overtime request.',
        ];
    }

    /**
     * Get the validated data with additional fields.
     *
     * @return array<string, mixed>
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();
        $validated['created_by'] = $this->user()?->id;

        return $validated;
    }
}
