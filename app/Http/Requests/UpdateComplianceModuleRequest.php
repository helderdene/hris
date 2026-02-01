<?php

namespace App\Http\Requests;

use App\Enums\ComplianceModuleContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateComplianceModuleRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'content_type' => ['sometimes', 'string', Rule::in(ComplianceModuleContentType::values())],
            'content' => ['nullable', 'string', 'max:50000'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'file' => ['nullable', 'file', 'max:100000'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_required' => ['boolean'],
            'passing_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'settings' => ['nullable', 'array'],
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
            'content_type.in' => 'The selected content type is invalid.',
            'external_url.url' => 'The external URL must be a valid URL.',
            'passing_score.min' => 'Passing score must be at least 0.',
            'passing_score.max' => 'Passing score cannot exceed 100.',
        ];
    }
}
