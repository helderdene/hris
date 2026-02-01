<?php

namespace App\Http\Requests;

use App\Enums\ReferenceRecommendation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferenceCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'referee_name' => ['sometimes', 'required', 'string', 'max:255'],
            'referee_email' => ['nullable', 'email', 'max:255'],
            'referee_phone' => ['nullable', 'string', 'max:50'],
            'referee_company' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', 'string', 'max:255'],
            'contacted' => ['sometimes', 'boolean'],
            'contacted_at' => ['nullable', 'date'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'recommendation' => ['nullable', 'string', Rule::in(ReferenceRecommendation::values())],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
