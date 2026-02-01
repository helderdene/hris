<?php

namespace Database\Factories;

use App\Enums\DeductionType;
use App\Models\PayrollDeduction;
use App\Models\PayrollEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollDeduction>
 */
class PayrollDeductionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PayrollDeduction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(DeductionType::cases());

        return [
            'payroll_entry_id' => PayrollEntry::factory(),
            'deduction_type' => $type,
            'deduction_code' => strtoupper($type->value),
            'description' => $type->label(),
            'basis_amount' => fake()->randomFloat(2, 15000, 80000),
            'rate' => 0,
            'amount' => fake()->randomFloat(2, 100, 2000),
            'is_employee_share' => true,
            'is_employer_share' => false,
            'remarks' => null,
            'contribution_table_type' => null,
            'contribution_table_id' => null,
        ];
    }

    /**
     * Create an SSS employee deduction.
     */
    public function sssEmployee(?float $amount = null, ?float $basis = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => DeductionType::Sss,
            'deduction_code' => 'SSS_EE',
            'description' => 'SSS Employee Share',
            'basis_amount' => $basis ?? fake()->randomFloat(2, 15000, 80000),
            'rate' => 0.045,
            'amount' => $amount ?? fake()->randomFloat(2, 500, 1500),
            'is_employee_share' => true,
            'is_employer_share' => false,
            'contribution_table_type' => 'sss_contribution_tables',
        ]);
    }

    /**
     * Create an SSS employer deduction.
     */
    public function sssEmployer(?float $amount = null, ?float $basis = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => DeductionType::Sss,
            'deduction_code' => 'SSS_ER',
            'description' => 'SSS Employer Share',
            'basis_amount' => $basis ?? fake()->randomFloat(2, 15000, 80000),
            'rate' => 0.095,
            'amount' => $amount ?? fake()->randomFloat(2, 1000, 3000),
            'is_employee_share' => false,
            'is_employer_share' => true,
            'contribution_table_type' => 'sss_contribution_tables',
        ]);
    }

    /**
     * Create a PhilHealth employee deduction.
     */
    public function philhealthEmployee(?float $amount = null, ?float $basis = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => DeductionType::Philhealth,
            'deduction_code' => 'PHIC_EE',
            'description' => 'PhilHealth Employee Share',
            'basis_amount' => $basis ?? fake()->randomFloat(2, 15000, 80000),
            'rate' => 0.025,
            'amount' => $amount ?? fake()->randomFloat(2, 200, 800),
            'is_employee_share' => true,
            'is_employer_share' => false,
            'contribution_table_type' => 'philhealth_contribution_tables',
        ]);
    }

    /**
     * Create a PhilHealth employer deduction.
     */
    public function philhealthEmployer(?float $amount = null, ?float $basis = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => DeductionType::Philhealth,
            'deduction_code' => 'PHIC_ER',
            'description' => 'PhilHealth Employer Share',
            'basis_amount' => $basis ?? fake()->randomFloat(2, 15000, 80000),
            'rate' => 0.025,
            'amount' => $amount ?? fake()->randomFloat(2, 200, 800),
            'is_employee_share' => false,
            'is_employer_share' => true,
            'contribution_table_type' => 'philhealth_contribution_tables',
        ]);
    }

    /**
     * Create a Pag-IBIG employee deduction.
     */
    public function pagibigEmployee(float $amount = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => DeductionType::Pagibig,
            'deduction_code' => 'HDMF_EE',
            'description' => 'Pag-IBIG Employee Share',
            'basis_amount' => fake()->randomFloat(2, 15000, 80000),
            'rate' => 0.02,
            'amount' => $amount,
            'is_employee_share' => true,
            'is_employer_share' => false,
            'contribution_table_type' => 'pagibig_contribution_tables',
        ]);
    }

    /**
     * Create a Pag-IBIG employer deduction.
     */
    public function pagibigEmployer(float $amount = 200): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => DeductionType::Pagibig,
            'deduction_code' => 'HDMF_ER',
            'description' => 'Pag-IBIG Employer Share',
            'basis_amount' => fake()->randomFloat(2, 15000, 80000),
            'rate' => 0.02,
            'amount' => $amount,
            'is_employee_share' => false,
            'is_employer_share' => true,
            'contribution_table_type' => 'pagibig_contribution_tables',
        ]);
    }

    /**
     * Create a withholding tax deduction.
     */
    public function withholdingTax(?float $amount = null, ?float $taxableIncome = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => DeductionType::WithholdingTax,
            'deduction_code' => 'TAX',
            'description' => 'Withholding Tax',
            'basis_amount' => $taxableIncome ?? fake()->randomFloat(2, 20000, 100000),
            'rate' => 0,
            'amount' => $amount ?? fake()->randomFloat(2, 0, 10000),
            'is_employee_share' => true,
            'is_employer_share' => false,
            'contribution_table_type' => 'withholding_tax_tables',
        ]);
    }

    /**
     * Create a loan deduction.
     */
    public function loan(?string $name = null, ?float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'deduction_type' => DeductionType::Loan,
            'deduction_code' => 'LOAN',
            'description' => $name ?? 'Loan Payment',
            'basis_amount' => 0,
            'rate' => 0,
            'amount' => $amount ?? fake()->randomFloat(2, 500, 5000),
            'is_employee_share' => true,
            'is_employer_share' => false,
        ]);
    }

    /**
     * Set a specific payroll entry for the deduction.
     */
    public function forEntry(PayrollEntry $entry): static
    {
        return $this->state(fn (array $attributes) => [
            'payroll_entry_id' => $entry->id,
        ]);
    }
}
