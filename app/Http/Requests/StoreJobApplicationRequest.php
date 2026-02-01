<?php

namespace App\Http\Requests;

use App\Enums\ApplicationSource;
use App\Models\Candidate;
use App\Models\JobPosting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobApplicationRequest extends FormRequest
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
            'candidate_id' => ['required', 'integer', Rule::exists(Candidate::class, 'id')],
            'job_posting_id' => ['required', 'integer', Rule::exists(JobPosting::class, 'id')],
            'source' => ['nullable', 'string', Rule::in(ApplicationSource::values())],
            'notes' => ['nullable', 'string', 'max:5000'],
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
            'candidate_id.required' => 'Please select a candidate.',
            'candidate_id.exists' => 'The selected candidate does not exist.',
            'job_posting_id.required' => 'Please select a job posting.',
            'job_posting_id.exists' => 'The selected job posting does not exist.',
        ];
    }
}
