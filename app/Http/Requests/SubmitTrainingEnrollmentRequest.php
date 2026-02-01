<?php

namespace App\Http\Requests;

use App\Enums\SessionStatus;
use Illuminate\Foundation\Http\FormRequest;

class SubmitTrainingEnrollmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session && $session->status === SessionStatus::Scheduled;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:1000'],
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
            'reason.max' => 'Reason cannot exceed 1000 characters.',
        ];
    }
}
