<?php

namespace App\Http\Requests;

use App\Enums\InterviewType;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInterviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(InterviewType::values())],
            'title' => ['required', 'string', 'max:255'],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'duration_minutes' => ['required', 'integer', 'min:15', 'max:480'],
            'location' => ['nullable', 'string', 'max:500'],
            'meeting_url' => ['nullable', 'url', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'panelist_ids' => ['sometimes', 'array'],
            'panelist_ids.*' => ['integer', Rule::exists(Employee::class, 'id')],
            'lead_panelist_id' => ['nullable', 'integer', Rule::exists(Employee::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Please select an interview type.',
            'title.required' => 'Please enter a title for the interview.',
            'scheduled_at.required' => 'Please select a date and time.',
            'scheduled_at.after' => 'The interview must be scheduled in the future.',
            'duration_minutes.required' => 'Please enter a duration.',
            'duration_minutes.min' => 'The duration must be at least 15 minutes.',
            'meeting_url.url' => 'Please enter a valid URL.',
        ];
    }
}
