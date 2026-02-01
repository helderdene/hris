<?php

namespace App\Http\Requests;

use App\Enums\LeaveApplicationStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('leave_application');

        // Can only update draft applications
        return $application && $application->status === LeaveApplicationStatus::Draft;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'leave_type_id' => ['sometimes', 'required', 'integer', Rule::exists(LeaveType::class, 'id')],
            'start_date' => ['sometimes', 'required', 'date', 'after_or_equal:today'],
            'end_date' => ['sometimes', 'required', 'date', 'after_or_equal:start_date'],
            'is_half_day_start' => ['boolean'],
            'is_half_day_end' => ['boolean'],
            'reason' => ['sometimes', 'required', 'string', 'max:2000'],
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
            'leave_type_id.exists' => 'The selected leave type does not exist.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'reason.max' => 'Reason cannot exceed 2000 characters.',
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

            $this->validateLeaveBalance($validator);
            $this->validateNoOverlap($validator);
        });
    }

    /**
     * Validate sufficient leave balance.
     */
    protected function validateLeaveBalance($validator): void
    {
        $application = $this->route('leave_application');
        $employeeId = $application->employee_id;
        $leaveTypeId = $this->input('leave_type_id', $application->leave_type_id);
        $startDate = $this->input('start_date', $application->start_date->format('Y-m-d'));
        $endDate = $this->input('end_date', $application->end_date->format('Y-m-d'));

        $totalDays = LeaveApplication::calculateTotalDays(
            $startDate,
            $endDate,
            $this->boolean('is_half_day_start', $application->is_half_day_start),
            $this->boolean('is_half_day_end', $application->is_half_day_end)
        );

        $year = \Carbon\Carbon::parse($startDate)->year;
        $balance = LeaveBalance::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if ($balance && ! $balance->hasAvailableBalance($totalDays)) {
            $validator->errors()->add(
                'total_days',
                "Insufficient leave balance. Available: {$balance->available} days, Requested: {$totalDays} days."
            );
        }
    }

    /**
     * Validate no overlapping leave applications.
     */
    protected function validateNoOverlap($validator): void
    {
        $application = $this->route('leave_application');
        $employeeId = $application->employee_id;
        $startDate = $this->input('start_date', $application->start_date->format('Y-m-d'));
        $endDate = $this->input('end_date', $application->end_date->format('Y-m-d'));

        $hasOverlap = LeaveApplication::overlapping($employeeId, $startDate, $endDate, $application->id)->exists();

        if ($hasOverlap) {
            $validator->errors()->add(
                'start_date',
                'You already have a pending or approved leave during this period.'
            );
        }
    }

    /**
     * Get the validated data with calculated fields.
     *
     * @return array<string, mixed>
     */
    public function validatedWithCalculations(): array
    {
        $application = $this->route('leave_application');
        $validated = $this->validated();

        $startDate = $validated['start_date'] ?? $application->start_date->format('Y-m-d');
        $endDate = $validated['end_date'] ?? $application->end_date->format('Y-m-d');
        $isHalfDayStart = $validated['is_half_day_start'] ?? $application->is_half_day_start;
        $isHalfDayEnd = $validated['is_half_day_end'] ?? $application->is_half_day_end;

        $validated['total_days'] = LeaveApplication::calculateTotalDays(
            $startDate,
            $endDate,
            $isHalfDayStart,
            $isHalfDayEnd
        );

        return $validated;
    }
}
