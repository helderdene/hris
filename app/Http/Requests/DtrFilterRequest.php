<?php

namespace App\Http\Requests;

use App\Enums\DtrStatus;
use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class DtrFilterRequest extends FormRequest
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
            'employee_id' => ['nullable', Rule::exists(Employee::class, 'id')],
            'department_id' => ['nullable', Rule::exists(Department::class, 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'status' => ['nullable', new Enum(DtrStatus::class)],
            'needs_review' => ['nullable', 'boolean'],
            'overtime_pending' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'employee_id.exists' => 'The selected employee does not exist.',
            'department_id.exists' => 'The selected department does not exist.',
            'date_from.date' => 'The start date must be a valid date.',
            'date_to.date' => 'The end date must be a valid date.',
            'date_to.after_or_equal' => 'The end date must be after or equal to the start date.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected status is invalid.',
            'per_page.min' => 'Items per page must be at least 1.',
            'per_page.max' => 'Items per page must not exceed 100.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default date range to current month if not provided
        if (! $this->has('date_from') && ! $this->has('date_to')) {
            $this->merge([
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->endOfMonth()->toDateString(),
            ]);
        }
    }
}
