<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
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
        $employee = $this->route('employee');
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return [
            // Required fields (unique rules ignore current record)
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Employee::class, 'employee_number')->ignore($employeeId),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique(Employee::class, 'email')->ignore($employeeId),
            ],
            'hire_date' => ['required', 'date'],

            // Personal info
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'civil_status' => ['nullable', 'string', Rule::in(['single', 'married', 'widowed', 'separated', 'divorced'])],
            'nationality' => ['nullable', 'string', 'max:100'],
            'fathers_name' => ['nullable', 'string', 'max:255'],
            'mothers_name' => ['nullable', 'string', 'max:255'],

            // Government IDs (no format validation per spec)
            'tin' => ['nullable', 'string', 'max:50'],
            'sss_number' => ['nullable', 'string', 'max:50'],
            'philhealth_number' => ['nullable', 'string', 'max:50'],
            'pagibig_number' => ['nullable', 'string', 'max:50'],
            'umid' => ['nullable', 'string', 'max:50'],
            'passport_number' => ['nullable', 'string', 'max:50'],
            'drivers_license' => ['nullable', 'string', 'max:50'],
            'nbi_clearance' => ['nullable', 'string', 'max:50'],
            'police_clearance' => ['nullable', 'string', 'max:50'],
            'prc_license' => ['nullable', 'string', 'max:50'],

            // Employment relationships
            'department_id' => ['nullable', 'integer', Rule::exists(Department::class, 'id')],
            'position_id' => ['nullable', 'integer', Rule::exists(Position::class, 'id')],
            'work_location_id' => ['nullable', 'integer', Rule::exists(WorkLocation::class, 'id')],
            'supervisor_id' => [
                'nullable',
                'integer',
                Rule::exists(Employee::class, 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($employeeId) {
                    // Cannot set self as supervisor
                    if ((int) $value === (int) $employeeId) {
                        $fail('An employee cannot be their own supervisor.');
                    }
                },
            ],

            // Employment details
            'employment_type' => ['nullable', 'string', Rule::in(EmploymentType::values())],
            'employment_status' => ['nullable', 'string', Rule::in(EmploymentStatus::values())],
            'regularization_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0', 'max:999999999.99'],
            'pay_frequency' => ['nullable', 'string', Rule::in(['monthly', 'semi-monthly', 'weekly', 'bi-weekly'])],

            // JSON fields
            'address' => ['nullable', 'array'],
            'address.street' => ['nullable', 'string', 'max:255'],
            'address.barangay' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:255'],
            'address.province' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],

            'emergency_contact' => ['nullable', 'array'],
            'emergency_contact.name' => ['nullable', 'string', 'max:255'],
            'emergency_contact.relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact.phone' => ['nullable', 'string', 'max:30'],

            'education' => ['nullable', 'array'],
            'education.highest_attainment' => ['nullable', 'string', 'max:100'],
            'education.school_name' => ['nullable', 'string', 'max:255'],
            'education.course' => ['nullable', 'string', 'max:255'],
            'education.year_graduated' => ['nullable', 'string', 'max:10'],

            'work_history' => ['nullable', 'array'],
            'work_history.*.company_name' => ['required_with:work_history', 'string', 'max:255'],
            'work_history.*.position' => ['nullable', 'string', 'max:255'],
            'work_history.*.start_date' => ['nullable', 'date'],
            'work_history.*.end_date' => ['nullable', 'date'],
            'work_history.*.reason_for_leaving' => ['nullable', 'string', 'max:500'],
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
            'employee_number.required' => 'The employee number is required.',
            'employee_number.unique' => 'This employee number is already in use.',
            'first_name.required' => 'The first name is required.',
            'last_name.required' => 'The last name is required.',
            'email.required' => 'The email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'hire_date.required' => 'The hire date is required.',
            'hire_date.date' => 'Please enter a valid hire date.',
            'date_of_birth.before' => 'The date of birth must be a date before today.',
            'regularization_date.after_or_equal' => 'The regularization date must be on or after the hire date.',
            'termination_date.after_or_equal' => 'The termination date must be on or after the hire date.',
            'department_id.exists' => 'The selected department does not exist.',
            'position_id.exists' => 'The selected position does not exist.',
            'work_location_id.exists' => 'The selected work location does not exist.',
            'supervisor_id.exists' => 'The selected supervisor does not exist.',
            'employment_type.in' => 'The selected employment type is invalid.',
            'employment_status.in' => 'The selected employment status is invalid.',
            'basic_salary.numeric' => 'The basic salary must be a number.',
            'basic_salary.min' => 'The basic salary cannot be negative.',
        ];
    }
}
