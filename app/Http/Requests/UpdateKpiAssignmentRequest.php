<?php

namespace App\Http\Requests;

use App\Enums\KpiAssignmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateKpiAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_value' => ['sometimes', 'numeric', 'min:0'],
            'weight' => ['sometimes', 'numeric', 'min:0', 'max:10'],
            'status' => ['sometimes', new Enum(KpiAssignmentStatus::class)],
            'notes' => ['nullable', 'string'],
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
            'target_value.min' => 'The target value must be a positive number.',
            'weight.min' => 'The weight must be a positive number.',
            'weight.max' => 'The weight must not exceed 10.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected status is invalid.',
        ];
    }
}
