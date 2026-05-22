<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\ManualAttendanceRequest;
use Illuminate\Foundation\Http\FormRequest;

class StoreManualAttendanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();

        return $employee !== null
            && (int) $this->input('employee_id') === $employee->id;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:tenant.employees,id'],
            'attendance_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after_or_equal:'.now()->subDays(60)->toDateString(),
            ],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i', 'after:time_in'],
            'reason' => ['required', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $timeIn = $this->input('time_in');
            $timeOut = $this->input('time_out');

            if (! $timeIn && ! $timeOut) {
                $validator->errors()->add(
                    'time_in',
                    'Please provide a time in, a time out, or both.'
                );
            }

            if (! $validator->errors()->has('employee_id') && ! $validator->errors()->has('attendance_date')) {
                $this->validateNoOverlap($validator);
            }
        });
    }

    protected function validateNoOverlap($validator): void
    {
        $exists = ManualAttendanceRequest::query()
            ->where('employee_id', $this->input('employee_id'))
            ->where('attendance_date', $this->input('attendance_date'))
            ->whereIn('status', ['draft', 'pending', 'approved'])
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                'attendance_date',
                'A manual attendance request already exists for this date.'
            );
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attendance_date.before_or_equal' => 'The attendance date cannot be in the future.',
            'attendance_date.after_or_equal' => 'The attendance date is too far in the past (60 day limit).',
            'time_out.after' => 'Time out must be later than time in.',
            'reason.required' => 'Please describe why this manual attendance request is needed.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedWithDefaults(): array
    {
        $validated = $this->validated();
        $validated['created_by'] = $this->user()?->id;

        return $validated;
    }
}
