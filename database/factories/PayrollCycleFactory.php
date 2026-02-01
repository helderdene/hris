<?php

namespace Database\Factories;

use App\Enums\PayrollCycleType;
use App\Models\PayrollCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollCycle>
 */
class PayrollCycleFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PayrollCycle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cycleType = fake()->randomElement(PayrollCycleType::cases());

        return [
            'name' => $this->getNameForType($cycleType),
            'code' => strtoupper(fake()->unique()->lexify('PAY-????')),
            'cycle_type' => $cycleType,
            'description' => fake()->optional()->sentence(),
            'status' => 'active',
            'cutoff_rules' => PayrollCycle::getDefaultCutoffRules($cycleType),
            'is_default' => false,
        ];
    }

    /**
     * Get a meaningful name for a cycle type.
     */
    private function getNameForType(PayrollCycleType $type): string
    {
        return match ($type) {
            PayrollCycleType::SemiMonthly => 'Semi-Monthly Payroll',
            PayrollCycleType::Monthly => 'Monthly Payroll',
            PayrollCycleType::Supplemental => 'Supplemental Payroll',
            PayrollCycleType::ThirteenthMonth => '13th Month Pay',
            PayrollCycleType::FinalPay => 'Final Pay Processing',
        };
    }

    /**
     * Indicate that this is an active cycle.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that this is an inactive cycle.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that this is the default cycle.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }

    /**
     * Create a semi-monthly cycle.
     */
    public function semiMonthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Semi-Monthly Payroll',
            'cycle_type' => PayrollCycleType::SemiMonthly,
            'cutoff_rules' => PayrollCycle::getDefaultCutoffRules(PayrollCycleType::SemiMonthly),
        ]);
    }

    /**
     * Create a monthly cycle.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Monthly Payroll',
            'cycle_type' => PayrollCycleType::Monthly,
            'cutoff_rules' => PayrollCycle::getDefaultCutoffRules(PayrollCycleType::Monthly),
        ]);
    }

    /**
     * Create a supplemental cycle.
     */
    public function supplemental(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Supplemental Payroll',
            'cycle_type' => PayrollCycleType::Supplemental,
            'cutoff_rules' => [],
        ]);
    }

    /**
     * Create a 13th month pay cycle.
     */
    public function thirteenthMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => '13th Month Pay',
            'cycle_type' => PayrollCycleType::ThirteenthMonth,
            'cutoff_rules' => [
                'computation_date' => 'december_5',
                'pay_day' => 24,
                'pay_month' => 12,
            ],
        ]);
    }

    /**
     * Create a final pay cycle.
     */
    public function finalPay(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Final Pay Processing',
            'cycle_type' => PayrollCycleType::FinalPay,
            'cutoff_rules' => [],
        ]);
    }

    /**
     * Set a specific code for the cycle.
     */
    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => $code,
        ]);
    }

    /**
     * Set custom cutoff rules.
     *
     * @param  array<string, mixed>  $rules
     */
    public function withCutoffRules(array $rules): static
    {
        return $this->state(fn (array $attributes) => [
            'cutoff_rules' => $rules,
        ]);
    }
}
