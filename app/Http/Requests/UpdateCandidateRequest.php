<?php

namespace App\Http\Requests;

use App\Enums\EducationLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCandidateRequest extends FormRequest
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
            'first_name' => ['sometimes', 'string', 'max:255'],
            'last_name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'portfolio_url' => ['nullable', 'url', 'max:500'],
            'resume' => ['nullable', 'file', 'mimes:pdf,docx', 'max:5120'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'education' => ['nullable', 'array'],
            'education.*.education_level' => ['required', 'string', Rule::in(EducationLevel::values())],
            'education.*.institution' => ['required', 'string', 'max:255'],
            'education.*.field_of_study' => ['nullable', 'string', 'max:255'],
            'education.*.start_date' => ['nullable', 'date'],
            'education.*.end_date' => ['nullable', 'date'],
            'education.*.is_current' => ['nullable', 'boolean'],
            'work_experience' => ['nullable', 'array'],
            'work_experience.*.company' => ['required', 'string', 'max:255'],
            'work_experience.*.job_title' => ['required', 'string', 'max:255'],
            'work_experience.*.description' => ['nullable', 'string'],
            'work_experience.*.start_date' => ['nullable', 'date'],
            'work_experience.*.end_date' => ['nullable', 'date'],
            'work_experience.*.is_current' => ['nullable', 'boolean'],
        ];
    }
}
