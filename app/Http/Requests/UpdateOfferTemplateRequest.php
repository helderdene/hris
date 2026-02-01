<?php

namespace App\Http\Requests;

use App\Services\HtmlSanitizerService;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOfferTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_default' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
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
            'name.required' => 'Please provide a name for the template.',
            'content.required' => 'Please provide the template content.',
        ];
    }

    /**
     * Handle a passed validation attempt and sanitize HTML content.
     */
    protected function passedValidation(): void
    {
        if ($this->has('content')) {
            $this->merge([
                'content' => app(HtmlSanitizerService::class)->sanitize($this->input('content')),
            ]);
        }
    }
}
