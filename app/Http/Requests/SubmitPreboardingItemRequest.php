<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPreboardingItemRequest extends FormRequest
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $item = $this->route('item');

        $rules = [];

        if ($item && $item->type->value === 'document_upload') {
            $rules['file'] = ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png,xls,xlsx'];
        }

        if ($item && $item->type->value === 'form_field') {
            $rules['form_value'] = ['required', 'string', 'max:2000'];
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
            'file.required' => 'Please upload a document.',
            'file.max' => 'The file must not exceed 10MB.',
            'form_value.required' => 'Please provide the required information.',
        ];
    }
}
