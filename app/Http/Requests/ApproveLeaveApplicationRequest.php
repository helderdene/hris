<?php

namespace App\Http\Requests;

use App\Enums\LeaveApplicationStatus;
use Illuminate\Foundation\Http\FormRequest;

class ApproveLeaveApplicationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $application = $this->route('leave_application');

        return $application && $application->status === LeaveApplicationStatus::Pending;
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
