<?php

namespace App\Http\Requests;

use App\Enums\OvertimeRequestStatus;
use App\Enums\OvertimeType;
use App\Models\OvertimeRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOvertimeRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $request = $this->route('overtime_request');

        return $request && $request->status === OvertimeRequestStatus::Draft;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'overtime_date' => ['sometimes', 'date'],
            'expected_start_time' => ['nullable', 'date_format:H:i'],
            'expected_end_time' => ['nullable', 'date_format:H:i'],
            'expected_minutes' => ['sometimes', 'integer', 'min:30', 'max:720'],
            'overtime_type' => ['sometimes', Rule::in(OvertimeType::values())],
            'reason' => ['sometimes', 'string', 'max:2000'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->has('overtime_date') && ! $validator->errors()->has('overtime_date')) {
                $this->validateNoOverlap($validator);
            }
        });
    }

    /**
     * Validate no overlapping overtime requests for the same date.
     */
    protected function validateNoOverlap($validator): void
    {
        $overtimeRequest = $this->route('overtime_request');
        $overtimeDate = $this->input('overtime_date', $overtimeRequest->overtime_date->format('Y-m-d'));

        $exists = OvertimeRequest::query()
            ->where('employee_id', $overtimeRequest->employee_id)
            ->where('overtime_date', $overtimeDate)
            ->whereIn('status', ['draft', 'pending', 'approved'])
            ->where('id', '!=', $overtimeRequest->id)
            ->exists();

        if ($exists) {
            $validator->errors()->add(
                'overtime_date',
                'An overtime request already exists for this date.'
            );
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expected_minutes.min' => 'Overtime must be at least 30 minutes.',
            'expected_minutes.max' => 'Overtime cannot exceed 12 hours (720 minutes).',
        ];
    }
}
