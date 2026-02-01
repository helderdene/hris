<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
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
        $department = $this->route('department');
        $departmentId = $department instanceof Department ? $department->id : $department;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Department::class, 'code')->ignore($departmentId),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Department::class, 'id'),
                function (string $attribute, mixed $value, \Closure $fail) use ($departmentId) {
                    if ($value === null) {
                        return;
                    }

                    // Cannot set self as parent
                    if ((int) $value === (int) $departmentId) {
                        $fail('A department cannot be its own parent.');

                        return;
                    }

                    // Check for circular reference
                    $department = Department::find($departmentId);
                    if ($department && ! $department->validateNotCircularReference((int) $value)) {
                        $fail('This would create a circular reference in the department hierarchy.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],
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
            'name.required' => 'The department name is required.',
            'code.required' => 'The department code is required.',
            'code.unique' => 'This department code is already in use.',
            'parent_id.exists' => 'The selected parent department does not exist.',
            'status.required' => 'The department status is required.',
            'status.in' => 'The status must be either active or inactive.',
        ];
    }
}
