<?php

namespace App\Http\Requests;

use App\Enums\ProbationaryEvaluationStatus;
use Illuminate\Foundation\Http\FormRequest;

class ApproveProbationaryEvaluationRequest extends FormRequest
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
            'remarks' => ['nullable', 'string', 'max:1000'],
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
            'remarks.max' => 'Remarks cannot exceed 1000 characters.',
        ];
    }
}
