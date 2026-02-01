<?php

namespace App\Http\Requests;

use App\Enums\KeyResultMetricType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKeyResultRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $keyResult = $this->route('keyResult');

        return $keyResult && $keyResult->status !== 'completed';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'metric_type' => ['sometimes', 'required', 'string', Rule::in(KeyResultMetricType::values())],
            'metric_unit' => ['nullable', 'string', 'max:50'],
            'target_value' => ['sometimes', 'required', 'numeric'],
            'starting_value' => ['nullable', 'numeric'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
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
            'title.required' => 'Please enter a key result title.',
            'title.max' => 'Key result title cannot exceed 255 characters.',
            'metric_type.required' => 'Please select a metric type.',
            'metric_type.in' => 'Invalid metric type selected.',
            'target_value.required' => 'Please enter a target value.',
            'target_value.numeric' => 'Target value must be a number.',
        ];
    }
}
