<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Models\TrainingWaitlist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTrainingEnrollmentRequest extends FormRequest
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
            'training_session_id' => ['required', 'integer', Rule::exists(TrainingSession::class, 'id')],
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'notes' => ['nullable', 'string', 'max:1000'],
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
            'training_session_id.required' => 'Please select a training session.',
            'training_session_id.exists' => 'The selected session does not exist.',
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->any()) {
                return;
            }

            $this->validateSessionEnrollable($validator);
            $this->validateNotAlreadyEnrolled($validator);
            $this->validateNotOnWaitlist($validator);
        });
    }

    /**
     * Validate the session is available for enrollment.
     */
    protected function validateSessionEnrollable($validator): void
    {
        $session = TrainingSession::find($this->input('training_session_id'));

        if ($session && ! $session->status->isEnrollable()) {
            $validator->errors()->add('training_session_id', 'This session is not available for enrollment.');
        }
    }

    /**
     * Validate employee is not already enrolled.
     */
    protected function validateNotAlreadyEnrolled($validator): void
    {
        $exists = TrainingEnrollment::query()
            ->where('training_session_id', $this->input('training_session_id'))
            ->where('employee_id', $this->input('employee_id'))
            ->where('status', 'confirmed')
            ->exists();

        if ($exists) {
            $validator->errors()->add('employee_id', 'This employee is already enrolled in this session.');
        }
    }

    /**
     * Validate employee is not already on waitlist.
     */
    protected function validateNotOnWaitlist($validator): void
    {
        $exists = TrainingWaitlist::query()
            ->where('training_session_id', $this->input('training_session_id'))
            ->where('employee_id', $this->input('employee_id'))
            ->where('status', 'waiting')
            ->exists();

        if ($exists) {
            $validator->errors()->add('employee_id', 'This employee is already on the waitlist for this session.');
        }
    }
}
