<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitorVisitRequest extends FormRequest
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
            'work_location_id' => ['sometimes', 'required', Rule::exists(WorkLocation::class, 'id')],
            'host_employee_id' => ['nullable', Rule::exists(Employee::class, 'id')],
            'purpose' => ['sometimes', 'required', 'string', 'max:500'],
            'expected_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
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
            'work_location_id.exists' => 'The selected work location does not exist.',
            'purpose.required' => 'The purpose of the visit is required.',
        ];
    }
}
