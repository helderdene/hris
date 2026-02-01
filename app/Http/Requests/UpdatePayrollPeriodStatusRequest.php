<?php

namespace App\Http\Requests;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdatePayrollPeriodStatusRequest extends FormRequest
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
            'status' => ['required', new Enum(PayrollPeriodStatus::class)],
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
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            $period = $this->route('payroll_period');

            if (! $period instanceof PayrollPeriod) {
                $period = PayrollPeriod::find($this->route('payroll_period'));
            }

            if ($period === null) {
                return;
            }

            $newStatus = PayrollPeriodStatus::tryFrom($this->input('status'));

            if ($newStatus !== null && ! $period->canTransitionTo($newStatus)) {
                $validator->errors()->add(
                    'status',
                    "Cannot transition from {$period->status->label()} to {$newStatus->label()}."
                );
            }
        });
    }
}
