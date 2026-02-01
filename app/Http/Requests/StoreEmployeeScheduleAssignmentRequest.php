<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeScheduleAssignmentRequest extends FormRequest
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
            'employee_id' => [
                'required',
                'integer',
                Rule::exists(Employee::class, 'id'),
            ],
            'shift_name' => ['nullable', 'string', 'max:100'],
            'effective_date' => ['required', 'date', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:effective_date'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isEmpty()) {
                $this->validateNoOverlappingAssignments($validator);
            }
        });
    }

    /**
     * Validate that there are no overlapping assignments for the same employee.
     */
    protected function validateNoOverlappingAssignments(\Illuminate\Validation\Validator $validator): void
    {
        $employeeId = $this->input('employee_id');
        $effectiveDate = $this->input('effective_date');
        $endDate = $this->input('end_date');

        $query = EmployeeScheduleAssignment::where('employee_id', $employeeId)
            ->where(function ($query) use ($effectiveDate, $endDate) {
                // Check for overlapping date ranges
                // New assignment overlaps if:
                // - Its effective_date falls within an existing assignment's range
                // - Its end_date falls within an existing assignment's range
                // - It completely encompasses an existing assignment
                $query->where(function ($q) use ($endDate) {
                    // Existing assignment that hasn't ended
                    $q->whereNull('end_date')
                        ->where('effective_date', '<=', $endDate ?? '9999-12-31');
                })->orWhere(function ($q) use ($effectiveDate, $endDate) {
                    // Existing assignment with end_date
                    $q->whereNotNull('end_date')
                        ->where('effective_date', '<=', $endDate ?? '9999-12-31')
                        ->where('end_date', '>=', $effectiveDate);
                });
            });

        if ($query->exists()) {
            $validator->errors()->add('employee_id', 'This employee already has an overlapping schedule assignment for the specified date range.');
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
            'employee_id.required' => 'An employee must be selected.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'effective_date.required' => 'The effective date is required.',
            'effective_date.date_format' => 'The effective date must be in YYYY-MM-DD format.',
            'end_date.after_or_equal' => 'The end date must be on or after the effective date.',
        ];
    }
}
