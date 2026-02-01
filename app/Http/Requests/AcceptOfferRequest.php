<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptOfferRequest extends FormRequest
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
            'signer_name' => ['required', 'string', 'max:255'],
            'signer_email' => ['required', 'email', 'max:255'],
            'signature_data' => ['required', 'string'],
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
            'signer_name.required' => 'Please enter your full name.',
            'signer_email.required' => 'Please enter your email address.',
            'signature_data.required' => 'Please provide your signature.',
        ];
    }
}
