<?php

namespace App\Http\Requests;

use App\Enums\BackgroundCheckStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBackgroundCheckRequest extends FormRequest
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
            'check_type' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(BackgroundCheckStatus::values())],
            'provider' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }
}
