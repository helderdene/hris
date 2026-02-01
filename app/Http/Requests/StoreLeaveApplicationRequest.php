<?php

namespace App\Http\Requests;

use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveApplicationRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', Rule::exists(Employee::class, 'id')],
            'leave_type_id' => ['required', 'integer', Rule::exists(LeaveType::class, 'id')],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day_start' => ['boolean'],
            'is_half_day_end' => ['boolean'],
            'reason' => ['required', 'string', 'max:2000'],
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
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'leave_type_id.required' => 'Please select a leave type.',
            'leave_type_id.exists' => 'The selected leave type does not exist.',
            'start_date.required' => 'Please select a start date.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.required' => 'Please select an end date.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'reason.required' => 'Please provide a reason for your leave request.',
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

            $this->validateEmployeeEligibility($validator);
            $this->validateLeaveBalance($validator);
            $this->validateNoOverlap($validator);
            $this->validateAdvanceNotice($validator);
        });
    }

    /**
     * Validate employee eligibility for the leave type.
     */
    protected function validateEmployeeEligibility($validator): void
    {
        $employee = Employee::find($this->input('employee_id'));
        $leaveType = LeaveType::find($this->input('leave_type_id'));

        if ($employee && $leaveType && ! $leaveType->isEmployeeEligible($employee)) {
            $validator->errors()->add('leave_type_id', 'You are not eligible for this leave type.');
        }
    }

    /**
     * Validate sufficient leave balance.
     */
    protected function validateLeaveBalance($validator): void
    {
        $employeeId = $this->input('employee_id');
        $leaveTypeId = $this->input('leave_type_id');
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        if (! $startDate || ! $endDate) {
            return;
        }

        $totalDays = LeaveApplication::calculateTotalDays(
            $startDate,
            $endDate,
            $this->boolean('is_half_day_start'),
            $this->boolean('is_half_day_end')
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
        $employeeId = $this->input('employee_id');
        $startDate = $this->input('start_date');
        $endDate = $this->input('end_date');

        if (! $startDate || ! $endDate) {
            return;
        }

        $hasOverlap = LeaveApplication::overlapping($employeeId, $startDate, $endDate)->exists();

        if ($hasOverlap) {
            $validator->errors()->add(
                'start_date',
                'You already have a pending or approved leave during this period.'
            );
        }
    }

    /**
     * Validate advance notice requirement.
     */
    protected function validateAdvanceNotice($validator): void
    {
        $leaveType = LeaveType::find($this->input('leave_type_id'));
        $startDate = $this->input('start_date');

        if (! $leaveType || ! $startDate) {
            return;
        }

        if ($leaveType->min_days_advance_notice > 0) {
            $daysAdvance = now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($startDate)->startOfDay(), false);

            if ($daysAdvance < $leaveType->min_days_advance_notice) {
                $validator->errors()->add(
                    'start_date',
                    "This leave type requires at least {$leaveType->min_days_advance_notice} days advance notice."
                );
            }
        }
    }

    /**
     * Get the validated data with calculated fields.
     *
     * @return array<string, mixed>
     */
    public function validatedWithCalculations(): array
    {
        $validated = $this->validated();

        $validated['total_days'] = LeaveApplication::calculateTotalDays(
            $validated['start_date'],
            $validated['end_date'],
            $validated['is_half_day_start'] ?? false,
            $validated['is_half_day_end'] ?? false
        );

        $validated['created_by'] = auth()->id();

        return $validated;
    }
}
