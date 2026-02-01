<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCompetencyEvaluationRequest extends FormRequest
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
            'self_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'self_comments' => ['nullable', 'string'],
            'manager_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'manager_comments' => ['nullable', 'string'],
            'final_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'evidence' => ['nullable', 'array'],
            'evidence.*' => ['string'],
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
            'self_rating.min' => 'Self-rating must be at least 1.',
            'self_rating.max' => 'Self-rating must not exceed 5.',
            'manager_rating.min' => 'Manager rating must be at least 1.',
            'manager_rating.max' => 'Manager rating must not exceed 5.',
            'final_rating.min' => 'Final rating must be at least 1.',
            'final_rating.max' => 'Final rating must not exceed 5.',
        ];
    }
}
