<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveDtrReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resolution_type' => ['required', Rule::in([
                'manual_time_out',
                'use_schedule_end',
                'mark_half_day',
                'mark_absent',
                'no_change',
            ])],
            'manual_time_out' => ['required_if:resolution_type,manual_time_out', 'nullable', 'date_format:H:i'],
            'remarks' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'resolution_type.required' => 'Please select how to resolve this review.',
            'manual_time_out.required_if' => 'Please enter the time-out when using manual entry.',
            'manual_time_out.date_format' => 'Time-out must be in HH:MM format.',
            'remarks.required' => 'Remarks are required when resolving a review.',
        ];
    }
}
