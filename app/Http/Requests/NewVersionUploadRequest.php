<?php

namespace App\Http\Requests;

use App\Services\DocumentStorageService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for uploading a new version of an existing document.
 *
 * Only requires the file and optional version notes - category and name
 * are inherited from the parent document.
 */
class NewVersionUploadRequest extends FormRequest
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
            'file.required' => 'A file is required for the new version.',
            'file.file' => 'The upload must be a valid file.',
            'file.max' => 'The file may not be larger than 10MB.',
            'file.mimetypes' => 'The file must be a PDF, DOC, DOCX, JPG, PNG, XLS, or XLSX file.',
            'version_notes.max' => 'The version notes may not exceed 1000 characters.',
        ];
    }
}
