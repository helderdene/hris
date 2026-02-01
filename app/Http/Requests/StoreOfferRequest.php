<?php

namespace App\Http\Requests;

use App\Models\JobApplication;
use App\Models\OfferTemplate;
use App\Services\HtmlSanitizerService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOfferRequest extends FormRequest
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
            'job_application_id' => ['required', 'integer', Rule::exists(JobApplication::class, 'id')],
            'offer_template_id' => ['nullable', 'integer', Rule::exists(OfferTemplate::class, 'id')],
            'content' => ['nullable', 'string'],
            'salary' => ['required', 'numeric', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'salary_frequency' => ['nullable', 'string', 'max:50'],
            'benefits' => ['nullable', 'array'],
            'benefits.*' => ['string', 'max:500'],
            'terms' => ['nullable', 'string'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'expiry_date' => ['nullable', 'date', 'after:today'],
            'position_title' => ['required', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'work_location' => ['nullable', 'string', 'max:255'],
            'employment_type' => ['nullable', 'string', 'max:50'],
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
            'job_application_id.required' => 'Please select a job application.',
            'job_application_id.exists' => 'The selected job application does not exist.',
            'salary.required' => 'Please enter the salary amount.',
            'start_date.required' => 'Please select a start date.',
            'position_title.required' => 'Please enter the position title.',
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
