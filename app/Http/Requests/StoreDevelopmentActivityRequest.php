<?php

namespace App\Http\Requests;

use App\Enums\DevelopmentActivityType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDevelopmentActivityRequest extends FormRequest
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
            'activity_type' => ['required', Rule::in(DevelopmentActivityType::values())],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'resource_url' => ['nullable', 'url', 'max:2048'],
            'due_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'activity_type.required' => 'Please select an activity type.',
            'title.required' => 'Please provide a title for this activity.',
            'resource_url.url' => 'Please provide a valid URL.',
        ];
    }
}
