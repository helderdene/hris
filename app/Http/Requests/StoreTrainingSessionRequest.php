<?php

namespace App\Http\Requests;

use App\Enums\SessionStatus;
use App\Models\Course;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingSessionRequest extends FormRequest
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
            'course_id' => ['required', 'integer', Rule::exists(Course::class, 'id')],
            'title' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i', 'after:start_time'],
            'location' => ['nullable', 'string', 'max:255'],
            'virtual_link' => ['nullable', 'url', 'max:500'],
            'status' => ['nullable', 'string', Rule::in(SessionStatus::values())],
            'max_participants' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'instructor_employee_id' => ['nullable', 'integer', Rule::exists(Employee::class, 'id')],
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
            'course_id.required' => 'Please select a course.',
            'course_id.exists' => 'The selected course does not exist.',
            'start_date.required' => 'Please select a start date.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.required' => 'Please select an end date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'start_time.date_format' => 'Invalid time format. Use HH:MM.',
            'end_time.date_format' => 'Invalid time format. Use HH:MM.',
            'end_time.after' => 'End time must be after start time.',
            'virtual_link.url' => 'Please enter a valid URL for the virtual link.',
            'status.in' => 'Invalid session status.',
            'instructor_employee_id.exists' => 'The selected instructor does not exist.',
        ];
    }
}
