<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TenantLogoUploadRequest extends FormRequest
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
            'logo' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,svg',
                'max:2048', // 2MB max
            ],
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
            'logo.required' => 'Please select a logo image to upload.',
            'logo.image' => 'The file must be an image.',
            'logo.mimes' => 'The logo must be a PNG, JPG, JPEG, or SVG file.',
            'logo.max' => 'The logo must not be larger than 2MB.',
        ];
    }
}
