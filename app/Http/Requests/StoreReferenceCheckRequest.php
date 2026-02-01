<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Enums\ReferenceRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReferenceCheckRequest extends FormRequest
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
            'referee_name' => ['required', 'string', 'max:255'],
            'referee_email' => ['nullable', 'email', 'max:255'],
            'referee_phone' => ['nullable', 'string', 'max:50'],
            'referee_company' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'contacted' => ['sometimes', 'boolean'],
            'contacted_at' => ['nullable', 'date'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['nullable', 'string', Rule::in(ReferenceRecommendation::values())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'referee_name.required' => 'Please enter the referee\'s name.',
            'referee_email.email' => 'Please enter a valid email address.',
        ];
    }

    /**
     * Additional validation for stage gating.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $application = $this->route('jobApplication');
            if ($application && ! $application->status->isAtOrPast(ApplicationStatus::Assessment)) {
                $validator->errors()->add('status', 'Reference checks can only be added from the Assessment stage onwards.');
            }
        });
    }
}
