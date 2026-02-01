<?php

namespace App\Http\Requests;

use App\Enums\CourseMaterialType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseMaterialRequest extends FormRequest
{
    /**
     * Maximum file size in kilobytes (50MB).
     */
    private const MAX_FILE_SIZE_KB = 51200;

    /**
     * Allowed MIME types for document uploads.
     *
     * @var array<string>
     */
    private const DOCUMENT_MIMES = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain',
    ];

    /**
     * Allowed MIME types for video uploads.
     *
     * @var array<string>
     */
    private const VIDEO_MIMES = [
        'video/mp4',
        'video/webm',
        'video/quicktime',
        'video/x-msvideo',
    ];

    /**
     * Allowed MIME types for image uploads.
     *
     * @var array<string>
     */
    private const IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

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
        $materialType = $this->input('material_type');

        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'material_type' => ['required', 'string', Rule::in(CourseMaterialType::values())],
            'external_url' => ['nullable', 'url', 'max:2048'],
        ];

        if ($materialType === CourseMaterialType::Link->value) {
            $rules['external_url'] = ['required', 'url', 'max:2048'];
        } else {
            $rules['file'] = ['required', 'file', 'max:'.self::MAX_FILE_SIZE_KB];

            $allowedMimes = match ($materialType) {
                CourseMaterialType::Document->value => self::DOCUMENT_MIMES,
                CourseMaterialType::Video->value => self::VIDEO_MIMES,
                CourseMaterialType::Image->value => self::IMAGE_MIMES,
                default => array_merge(self::DOCUMENT_MIMES, self::VIDEO_MIMES, self::IMAGE_MIMES),
            };

            $rules['file'][] = 'mimetypes:'.implode(',', $allowedMimes);
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
            'title.required' => 'The material title is required.',
            'title.max' => 'The title cannot exceed 255 characters.',
            'material_type.required' => 'The material type is required.',
            'material_type.in' => 'The selected material type is invalid.',
            'file.required' => 'A file is required for this material type.',
            'file.max' => 'The file size cannot exceed 50MB.',
            'file.mimetypes' => 'The file type is not allowed for this material type.',
            'external_url.required' => 'A URL is required for link materials.',
            'external_url.url' => 'Please enter a valid URL.',
        ];
    }

    /**
     * Get all allowed MIME types.
     *
     * @return array<string>
     */
    public static function getAllowedMimeTypes(): array
    {
        return array_merge(self::DOCUMENT_MIMES, self::VIDEO_MIMES, self::IMAGE_MIMES);
    }

    /**
     * Get the maximum file size in bytes.
     */
    public static function getMaxFileSizeBytes(): int
    {
        return self::MAX_FILE_SIZE_KB * 1024;
    }
}
