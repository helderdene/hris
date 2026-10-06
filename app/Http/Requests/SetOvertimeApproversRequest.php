<?php

namespace App\Http\Requests;

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SetOvertimeApproversRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('can-manage-organization');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_ids' => ['present', 'array'],
            'employee_ids.*' => [
                'integer',
                'distinct',
                Rule::exists(Employee::class, 'id')
                    ->where('employment_status', EmploymentStatus::Active->value),
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
        return [
            'employee_ids.present' => 'Please provide the list of overtime approvers.',
            'employee_ids.*.exists' => 'Each selected employee must be active.',
        ];
    }
}
