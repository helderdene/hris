<?php

namespace App\Http\Requests;

use App\Models\CertificationType;
use Illuminate\Foundation\Http\FormRequest;

class StoreCertificationRequest extends FormRequest
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
            'certification_type_id' => [
                'required',
                'integer',
                'exists:'.(new CertificationType)->getTable().',id',
            ],
            'certificate_number' => ['nullable', 'string', 'max:255'],
            'issuing_body' => ['required', 'string', 'max:255'],
            'issued_date' => ['required', 'date', 'before_or_equal:today'],
            'expiry_date' => ['nullable', 'date', 'after:issued_date'],
            'description' => ['nullable', 'string', 'max:1000'],
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
            'certification_type_id.required' => 'Please select a certification type.',
            'certification_type_id.exists' => 'The selected certification type does not exist.',
            'issuing_body.required' => 'The issuing body is required.',
            'issuing_body.max' => 'The issuing body must not exceed 255 characters.',
            'issued_date.required' => 'The issue date is required.',
            'issued_date.before_or_equal' => 'The issue date cannot be in the future.',
            'expiry_date.after' => 'The expiry date must be after the issue date.',
            'description.max' => 'The description must not exceed 1000 characters.',
        ];
    }
}
