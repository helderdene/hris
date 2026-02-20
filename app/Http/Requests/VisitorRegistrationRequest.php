<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\WorkLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VisitorRegistrationRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'purpose' => ['required', 'string', 'max:500'],
            'work_location_id' => ['required', Rule::exists(WorkLocation::class, 'id')],
            'host_employee_id' => ['required', Rule::exists(Employee::class, 'id')],
            'expected_at' => ['required', 'date', 'after:now'],
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
            'first_name.required' => 'Your first name is required.',
            'last_name.required' => 'Your last name is required.',
            'email.required' => 'Your email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'purpose.required' => 'Please describe the purpose of your visit.',
            'work_location_id.required' => 'Please select a location.',
            'work_location_id.exists' => 'The selected location is not valid.',
            'host_employee_id.required' => 'Please select the person you are visiting.',
            'host_employee_id.exists' => 'The selected host is not valid.',
            'expected_at.required' => 'Please select when you expect to visit.',
            'expected_at.after' => 'The visit date must be in the future.',
        ];
    }
}
