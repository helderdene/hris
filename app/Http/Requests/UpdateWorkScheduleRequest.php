<?php

namespace App\Http\Requests;

use App\Enums\ScheduleType;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdateWorkScheduleRequest extends FormRequest
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
        $workSchedule = $this->route('workSchedule');
        $workScheduleId = $workSchedule instanceof WorkSchedule ? $workSchedule->id : $workSchedule;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique(WorkSchedule::class, 'code')->ignore($workScheduleId),
            ],
            'schedule_type' => ['required', new Enum(ScheduleType::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'string', Rule::in(['active', 'inactive'])],

            // Time configuration - structure varies by schedule_type
            'time_configuration' => ['nullable', 'array'],
            'time_configuration.work_days' => ['nullable', 'array'],
            'time_configuration.work_days.*' => ['string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'time_configuration.half_day_saturday' => ['nullable', 'boolean'],
            'time_configuration.start_time' => ['nullable', 'string', 'date_format:H:i'],
            'time_configuration.end_time' => ['nullable', 'string', 'date_format:H:i'],
            'time_configuration.saturday_end_time' => ['nullable', 'string', 'date_format:H:i'],
            'time_configuration.break' => ['nullable', 'array'],
            'time_configuration.break.start_time' => ['nullable', 'string', 'date_format:H:i'],
            'time_configuration.break.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],

            // Flexible schedule specific
            'time_configuration.required_hours_per_day' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'time_configuration.required_hours_per_week' => ['nullable', 'numeric', 'min:1', 'max:168'],
            'time_configuration.core_hours' => ['nullable', 'array'],
            'time_configuration.core_hours.start_time' => ['nullable', 'string', 'date_format:H:i'],
            'time_configuration.core_hours.end_time' => ['nullable', 'string', 'date_format:H:i'],
            'time_configuration.flexible_start_window' => ['nullable', 'array'],
            'time_configuration.flexible_start_window.earliest' => ['nullable', 'string', 'date_format:H:i'],
            'time_configuration.flexible_start_window.latest' => ['nullable', 'string', 'date_format:H:i'],

            // Shifting schedule specific
            'time_configuration.shifts' => ['nullable', 'array'],
            'time_configuration.shifts.*.name' => ['required_with:time_configuration.shifts', 'string', 'max:100'],
            'time_configuration.shifts.*.start_time' => ['required_with:time_configuration.shifts', 'string', 'date_format:H:i'],
            'time_configuration.shifts.*.end_time' => ['required_with:time_configuration.shifts', 'string', 'date_format:H:i'],
            'time_configuration.shifts.*.break' => ['nullable', 'array'],
            'time_configuration.shifts.*.break.start_time' => ['nullable', 'string', 'date_format:H:i'],
            'time_configuration.shifts.*.break.duration_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],

            // Compressed schedule specific
            'time_configuration.pattern' => ['nullable', 'string', Rule::in(['4x10', '4.5-day'])],
            'time_configuration.daily_hours' => ['nullable', 'numeric', 'min:1', 'max:24'],
            'time_configuration.half_day' => ['nullable', 'array'],
            'time_configuration.half_day.enabled' => ['nullable', 'boolean'],
            'time_configuration.half_day.day' => ['nullable', 'string', Rule::in(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])],
            'time_configuration.half_day.hours' => ['nullable', 'numeric', 'min:1', 'max:12'],

            // Overtime rules
            'overtime_rules' => ['nullable', 'array'],
            'overtime_rules.daily_threshold_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'overtime_rules.weekly_threshold_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'overtime_rules.regular_multiplier' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'overtime_rules.rest_day_multiplier' => ['nullable', 'numeric', 'min:1', 'max:10'],
            'overtime_rules.holiday_multiplier' => ['nullable', 'numeric', 'min:1', 'max:10'],

            // Night differential
            'night_differential' => ['nullable', 'array'],
            'night_differential.enabled' => ['nullable', 'boolean'],
            'night_differential.start_time' => ['nullable', 'string', 'date_format:H:i'],
            'night_differential.end_time' => ['nullable', 'string', 'date_format:H:i'],
            'night_differential.rate_multiplier' => ['nullable', 'numeric', 'min:1', 'max:10'],
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
            'name.required' => 'The schedule name is required.',
            'code.required' => 'The schedule code is required.',
            'code.unique' => 'This schedule code is already in use.',
            'schedule_type.required' => 'The schedule type is required.',
            'schedule_type.Illuminate\Validation\Rules\Enum' => 'The selected schedule type is invalid.',
            'status.required' => 'The schedule status is required.',
            'status.in' => 'The status must be either active or inactive.',
            'time_configuration.work_days.*.in' => 'Invalid day specified in work days.',
            'time_configuration.start_time.date_format' => 'Start time must be in HH:MM format.',
            'time_configuration.end_time.date_format' => 'End time must be in HH:MM format.',
            'time_configuration.break.duration_minutes.max' => 'Break duration cannot exceed 8 hours.',
            'overtime_rules.regular_multiplier.min' => 'Overtime multiplier must be at least 1.',
            'night_differential.rate_multiplier.min' => 'Night differential rate must be at least 1.',
        ];
    }
}
