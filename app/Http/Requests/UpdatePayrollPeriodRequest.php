<?php

namespace App\Http\Requests;

use App\Enums\PayrollPeriodType;
use App\Models\PayrollCycle;
use App\Models\PayrollPeriod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class UpdatePayrollPeriodRequest extends FormRequest
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
        $periodId = $this->route('payroll_period')?->id ?? $this->route('payroll_period');
        $period = $this->route('payroll_period') instanceof PayrollPeriod
            ? $this->route('payroll_period')
            : PayrollPeriod::find($periodId);

        $cycleId = $this->input('payroll_cycle_id', $period?->payroll_cycle_id);
        $year = $this->input('year', $period?->year);

        return [
            'payroll_cycle_id' => ['sometimes', 'required', Rule::exists(PayrollCycle::class, 'id')],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'period_type' => ['sometimes', 'required', new Enum(PayrollPeriodType::class)],
            'year' => ['sometimes', 'required', 'integer', 'min:2000', 'max:2100'],
            'period_number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                'max:52',
                Rule::unique(PayrollPeriod::class)
                    ->where('payroll_cycle_id', $cycleId)
                    ->where('year', $year)
                    ->ignore($periodId),
            ],
            'cutoff_start' => ['sometimes', 'required', 'date'],
            'cutoff_end' => ['sometimes', 'required', 'date', 'after_or_equal:cutoff_start'],
            'pay_date' => ['sometimes', 'required', 'date'],
            'notes' => ['nullable', 'string'],
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
            'payroll_cycle_id.required' => 'A payroll cycle must be selected.',
            'payroll_cycle_id.exists' => 'The selected payroll cycle does not exist.',
            'name.required' => 'The period name is required.',
            'period_type.required' => 'The period type is required.',
            'period_type.Illuminate\Validation\Rules\Enum' => 'The selected period type is invalid.',
            'year.required' => 'The year is required.',
            'year.min' => 'The year must be 2000 or later.',
            'year.max' => 'The year must be 2100 or earlier.',
            'period_number.required' => 'The period number is required.',
            'period_number.unique' => 'A period with this number already exists for this cycle and year.',
            'cutoff_start.required' => 'The cutoff start date is required.',
            'cutoff_end.required' => 'The cutoff end date is required.',
            'cutoff_end.after_or_equal' => 'The cutoff end date must be on or after the start date.',
            'pay_date.required' => 'The pay date is required.',
        ];
    }
}
