<?php

namespace App\Http\Requests;

use App\Enums\EnrollmentStatus;
use Illuminate\Foundation\Http\FormRequest;

class RejectTrainingEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $enrollment = $this->route('enrollment');

        return $enrollment && $enrollment->status === EnrollmentStatus::Pending;
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
            'reason.required' => 'Please provide a reason for rejection.',
            'reason.max' => 'Reason cannot exceed 1000 characters.',
        ];
    }
}
