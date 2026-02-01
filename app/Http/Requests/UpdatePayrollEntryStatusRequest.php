<?php

namespace App\Http\Requests;

use App\Enums\PayrollEntryStatus;
use App\Models\PayrollEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePayrollEntryStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(PayrollEntryStatus::class)],
            'remarks' => ['nullable', 'string', 'max:1000'],
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
            'status.required' => 'The status is required.',
            'status.Illuminate\Validation\Rules\Enum' => 'The selected status is invalid.',
            'remarks.max' => 'Remarks cannot exceed 1000 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $entry = $this->route('payroll_entry');

            if (! $entry instanceof PayrollEntry) {
                $entry = PayrollEntry::find($this->route('payroll_entry'));
            }

            if ($entry === null) {
                return;
            }

            $newStatus = PayrollEntryStatus::tryFrom($this->input('status'));

            if ($newStatus !== null && ! $entry->canTransitionTo($newStatus)) {
                $validator->errors()->add(
                    'status',
                    "Cannot transition from {$entry->status->label()} to {$newStatus->label()}."
                );
            }
        });
    }
}
