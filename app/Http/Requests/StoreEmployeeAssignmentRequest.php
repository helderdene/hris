<?php

namespace App\Http\Requests;

use App\Enums\AssignmentType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeAssignmentRequest extends FormRequest
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
            'assignment_type' => ['required', 'string', Rule::in(AssignmentType::values())],
            'new_value_id' => ['required', 'integer', $this->getNewValueExistsRule()],
            'effective_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'set_as_department_head' => ['nullable', 'boolean'],
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
            'assignment_type.required' => 'The assignment type is required.',
            'assignment_type.in' => 'The selected assignment type is invalid. Valid types are: position, department, location, supervisor.',
            'new_value_id.required' => 'The new value is required.',
            'new_value_id.integer' => 'The new value must be a valid ID.',
            'new_value_id.exists' => 'The selected value does not exist.',
            'effective_date.required' => 'The effective date is required.',
            'effective_date.date' => 'Please enter a valid effective date.',
            'remarks.max' => 'The remarks may not exceed 1000 characters.',
        ];
    }

    /**
     * Get the validation rule for new_value_id based on assignment_type.
     */
    protected function getNewValueExistsRule(): string
    {
        $assignmentType = $this->input('assignment_type');

        return match ($assignmentType) {
            'position' => Rule::exists(Position::class, 'id'),
            'department' => Rule::exists(Department::class, 'id'),
            'location' => Rule::exists(WorkLocation::class, 'id'),
            'supervisor' => Rule::exists(Employee::class, 'id'),
            default => 'exists:departments,id', // Fallback, will fail validation anyway due to invalid assignment_type
        };
    }
}
