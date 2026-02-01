<?php

namespace App\Http\Requests;

use App\Enums\PerformanceCycleType;
use App\Models\PerformanceCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StorePerformanceCycleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', Rule::unique(PerformanceCycle::class, 'code')],
            'cycle_type' => ['required', new Enum(PerformanceCycleType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'is_default' => ['boolean'],
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
            'name.required' => 'The cycle name is required.',
            'name.max' => 'The cycle name must not exceed 255 characters.',
            'code.required' => 'The cycle code is required.',
            'code.unique' => 'This cycle code is already in use.',
            'code.max' => 'The cycle code must not exceed 50 characters.',
            'cycle_type.required' => 'The cycle type is required.',
            'cycle_type.Illuminate\Validation\Rules\Enum' => 'The selected cycle type is invalid.',
            'status.in' => 'The status must be either active or inactive.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->has('status')) {
            $this->merge(['status' => 'active']);
        }

        if (! $this->has('is_default')) {
            $this->merge(['is_default' => false]);
        }
    }
}
