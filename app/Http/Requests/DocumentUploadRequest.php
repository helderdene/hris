<?php

namespace App\Http\Requests;

use App\Services\DocumentStorageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DocumentUploadRequest extends FormRequest
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
            'file' => [
                'required',
                'file',
                'max:10240', // 10MB in kilobytes
                'mimetypes:'.implode(',', DocumentStorageService::ALLOWED_MIME_TYPES),
            ],
            'document_category_id' => [
                'required',
                'integer',
                Rule::exists('tenant.document_categories', 'id'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'version_notes' => ['nullable', 'string', 'max:1000'],
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
            'file.required' => 'A file is required for upload.',
            'file.file' => 'The upload must be a valid file.',
            'file.max' => 'The file may not be larger than 10MB.',
            'file.mimetypes' => 'The file must be a PDF, DOC, DOCX, JPG, PNG, XLS, or XLSX file.',
            'document_category_id.required' => 'Please select a document category.',
            'document_category_id.exists' => 'The selected category is invalid.',
            'name.required' => 'The document name is required.',
            'name.max' => 'The document name may not exceed 255 characters.',
            'version_notes.max' => 'The version notes may not exceed 1000 characters.',
        ];
    }
}
