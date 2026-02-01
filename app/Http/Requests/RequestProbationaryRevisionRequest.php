<?php

namespace App\Http\Requests;

use App\Enums\ProbationaryEvaluationStatus;
use Illuminate\Foundation\Http\FormRequest;

class RequestProbationaryRevisionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $evaluation = $this->route('probationary_evaluation');

        return $evaluation && in_array($evaluation->status, [
            ProbationaryEvaluationStatus::Submitted,
            ProbationaryEvaluationStatus::HrReview,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
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
            'reason.required' => 'A reason for requesting revision is required.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
        ];
    }
}
