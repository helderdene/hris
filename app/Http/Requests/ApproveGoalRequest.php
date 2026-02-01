<?php

namespace App\Http\Requests;

use App\Enums\GoalApprovalStatus;
use Illuminate\Foundation\Http\FormRequest;

class ApproveGoalRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $goal = $this->route('goal');

        return $goal && $goal->approval_status === GoalApprovalStatus::Pending;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'feedback' => ['nullable', 'string', 'max:2000'],
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
            'feedback.max' => 'Feedback cannot exceed 2000 characters.',
        ];
    }
}
