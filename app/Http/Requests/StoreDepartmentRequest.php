<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique(Department::class, 'code')],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Department::class, 'id'),
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
