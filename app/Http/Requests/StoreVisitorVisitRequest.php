<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\Visitor;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitorVisitRequest extends FormRequest
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
            'visitor_id' => ['required', Rule::exists(Visitor::class, 'id')],
            'work_location_id' => ['required', Rule::exists(WorkLocation::class, 'id')],
            'host_employee_id' => ['nullable', Rule::exists(Employee::class, 'id')],
            'purpose' => ['required', 'string', 'max:500'],
            'expected_at' => ['nullable', 'date', 'after:now'],
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
            'visitor_id.required' => 'A visitor must be selected.',
            'visitor_id.exists' => 'The selected visitor does not exist.',
            'work_location_id.required' => 'A work location must be selected.',
            'work_location_id.exists' => 'The selected work location does not exist.',
            'purpose.required' => 'The purpose of the visit is required.',
            'expected_at.after' => 'The expected date must be in the future.',
        ];
    }
}
