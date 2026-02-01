<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Enums\BackgroundCheckStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBackgroundCheckRequest extends FormRequest
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
            'check_type' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(BackgroundCheckStatus::values())],
            'provider' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'started_at' => ['nullable', 'date'],
            'completed_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'check_type.required' => 'Please enter a check type.',
        ];
    }

    /**
     * Additional validation for stage gating.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $application = $this->route('jobApplication');
            if ($application && ! $application->status->isAtOrPast(ApplicationStatus::Offer)) {
                $validator->errors()->add('status', 'Background checks can only be added from the Offer stage onwards.');
            }
        });
    }
}
