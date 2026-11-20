<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ClearanceItemRequest extends FormRequest
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
            'name' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:255'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
            'is_cleared' => ['sometimes', 'boolean'],
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
            'name.required' => 'The item name is required.',
            'name.max' => 'The item name may not exceed 255 characters.',
            'remarks.max' => 'The remarks may not exceed 500 characters.',
        ];
    }
}
