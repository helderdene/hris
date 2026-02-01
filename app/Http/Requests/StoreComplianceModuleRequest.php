<?php

namespace App\Http\Requests;

use App\Enums\ComplianceModuleContentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreComplianceModuleRequest extends FormRequest
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
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'content_type' => ['required', 'string', Rule::in(ComplianceModuleContentType::values())],
            'content' => ['nullable', 'string', 'max:50000'],
            'external_url' => ['nullable', 'url', 'max:500'],
            'duration_minutes' => ['nullable', 'integer', 'min:1', 'max:600'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_required' => ['boolean'],
            'passing_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'settings' => ['nullable', 'array'],
        ];

        // File validation based on content type
        $contentType = $this->input('content_type');
        if ($contentType === ComplianceModuleContentType::Pdf->value) {
            $rules['file'] = ['required', 'file', 'mimes:pdf', 'max:50000'];
        } elseif ($contentType === ComplianceModuleContentType::Scorm->value) {
            $rules['file'] = ['required', 'file', 'mimes:zip', 'max:100000'];
        } else {
            $rules['file'] = ['nullable', 'file', 'max:50000'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'The module title is required.',
            'content_type.required' => 'The content type is required.',
            'content_type.in' => 'The selected content type is invalid.',
            'file.required' => 'A file is required for this content type.',
            'file.mimes' => 'The file must be a valid format for this content type.',
            'external_url.url' => 'The external URL must be a valid URL.',
            'passing_score.min' => 'Passing score must be at least 0.',
            'passing_score.max' => 'Passing score cannot exceed 100.',
        ];
    }
}
