<?php

namespace App\Http\Requests;

use App\Enums\LoanStatus;
use App\Models\EmployeeLoan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateLoanStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(LoanStatus::class)],
            'notes' => ['nullable', 'string', 'max:500'],
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
            'status.required' => 'Please select a status.',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $loan = $this->route('loan');

            if (! $loan instanceof EmployeeLoan) {
                return;
            }

            $newStatus = LoanStatus::tryFrom($this->input('status'));

            if ($newStatus === null) {
                return;
            }

            if (! $loan->status->canTransitionTo($newStatus)) {
                $validator->errors()->add(
                    'status',
                    "Cannot change status from {$loan->status->label()} to {$newStatus->label()}."
                );
            }
        });
    }
}
