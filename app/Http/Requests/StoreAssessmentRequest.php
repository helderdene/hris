<?php

namespace App\Http\Requests;

use App\Enums\ApplicationStatus;
use App\Enums\AssessmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssessmentRequest extends FormRequest
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
            'test_name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::in(AssessmentType::values())],
            'score' => ['nullable', 'numeric', 'min:0'],
            'max_score' => ['nullable', 'numeric', 'min:0'],
            'passed' => ['nullable', 'boolean'],
            'assessed_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'test_name.required' => 'Please enter a test name.',
            'type.required' => 'Please select an assessment type.',
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
                $validator->errors()->add('status', 'Assessments can only be added from the Assessment stage onwards.');
            }
        });
    }
}
