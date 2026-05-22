<?php

namespace App\Http\Requests;

use App\Enums\ManualAttendanceRequestStatus;
use App\Models\Employee;
use App\Models\ManualAttendanceRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdateManualAttendanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('manual_attendance_request');

        if (! $request || $request->status !== ManualAttendanceRequestStatus::Draft) {
            return false;
        }

        $user = $this->user();

        if (! $user) {
            return false;
        }

        $employee = Employee::query()->where('user_id', $user->id)->first();

        return $employee !== null && $employee->id === $request->employee_id;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attendance_date' => [
                'sometimes',
                'date',
                'before_or_equal:today',
                'after_or_equal:'.now()->subDays(60)->toDateString(),
            ],
            'time_in' => ['nullable', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i', 'after:time_in'],
            'reason' => ['sometimes', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $manualRequest = $this->route('manual_attendance_request');

            $timeIn = $this->input('time_in', $manualRequest->time_in);
            $timeOut = $this->input('time_out', $manualRequest->time_out);

            if (! $timeIn && ! $timeOut) {
                $validator->errors()->add(
                    'time_in',
                    'Please provide a time in, a time out, or both.'
                );
            }

            if ($this->has('attendance_date') && ! $validator->errors()->has('attendance_date')) {
                $this->validateNoOverlap($validator);
            }
        });
    }

    protected function validateNoOverlap($validator): void
    {
        $manualRequest = $this->route('manual_attendance_request');

        $exists = ManualAttendanceRequest::query()
            ->where('employee_id', $manualRequest->employee_id)
            ->where('attendance_date', $this->input('attendance_date'))
            ->whereIn('status', ['draft', 'pending', 'approved'])
            ->where('id', '!=', $manualRequest->id)
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                'attendance_date',
                'A manual attendance request already exists for this date.'
            );
        }
    }
}
