<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WithholdingTaxTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WithholdingTaxTable>
 */
class WithholdingTaxTableFactory extends Factory
{
    protected $model = WithholdingTaxTable::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'pay_period' => fake()->randomElement(WithholdingTaxTable::PAY_PERIODS),
            'effective_from' => fake()->dateTimeBetween('-1 year', 'now'),
            'description' => 'Withholding Tax Table '.fake()->year(),
            'is_active' => true,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the table is for monthly pay period.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_period' => 'monthly',
        ]);
    }

    /**
     * Indicate that the table is for semi-monthly pay period.
     */
    public function semiMonthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_period' => 'semi_monthly',
        ]);
    }

    /**
     * Indicate that the table is for weekly pay period.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_period' => 'weekly',
        ]);
    }

    /**
     * Indicate that the table is for daily pay period.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_period' => 'daily',
        ]);
    }

    /**
     * Indicate that the table is for TRAIN Law 2023.
     */
    public function train2023(): static
    {
        return $this->state(fn (array $attributes) => [
            'effective_from' => '2023-01-01',
            'description' => 'TRAIN Law 2023 Withholding Tax Table',
        ]);
    }

    /**
     * Indicate that the table is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set the creator.
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
        ]);
    }

    /**
     * Configure the model factory to create with TRAIN Law brackets.
     * Brackets vary based on pay period.
     */
    public function withBrackets(): static
    {
        return $this->afterCreating(function (WithholdingTaxTable $table) {
            $brackets = $this->getBracketsForPayPeriod($table->pay_period);

            foreach ($brackets as $bracket) {
                $table->brackets()->create($bracket);
            }
        });
    }

    /**
     * Get brackets for a specific pay period based on TRAIN Law.
     *
     * @return array<array<string, mixed>>
     */
    protected function getBracketsForPayPeriod(string $payPeriod): array
    {
        return match ($payPeriod) {
            'monthly' => $this->getMonthlyBrackets(),
            'semi_monthly' => $this->getSemiMonthlyBrackets(),
            'weekly' => $this->getWeeklyBrackets(),
            'daily' => $this->getDailyBrackets(),
            default => $this->getMonthlyBrackets(),
        };
    }

    /**
     * Get the TRAIN Law monthly withholding tax brackets (2023 onwards).
     *
     * @return array<array<string, mixed>>
     */
    protected function getMonthlyBrackets(): array
    {
        return [
            ['min_compensation' => 0, 'max_compensation' => 20833, 'base_tax' => 0, 'excess_rate' => 0],
            ['min_compensation' => 20833, 'max_compensation' => 33333, 'base_tax' => 0, 'excess_rate' => 0.15],
            ['min_compensation' => 33333, 'max_compensation' => 66667, 'base_tax' => 1875, 'excess_rate' => 0.20],
            ['min_compensation' => 66667, 'max_compensation' => 166667, 'base_tax' => 8541.67, 'excess_rate' => 0.25],
            ['min_compensation' => 166667, 'max_compensation' => 666667, 'base_tax' => 33541.67, 'excess_rate' => 0.30],
            ['min_compensation' => 666667, 'max_compensation' => null, 'base_tax' => 183541.67, 'excess_rate' => 0.35],
        ];
    }

    /**
     * Get the TRAIN Law semi-monthly withholding tax brackets (2023 onwards).
     *
     * @return array<array<string, mixed>>
     */
    protected function getSemiMonthlyBrackets(): array
    {
        return [
            ['min_compensation' => 0, 'max_compensation' => 10417, 'base_tax' => 0, 'excess_rate' => 0],
            ['min_compensation' => 10417, 'max_compensation' => 16667, 'base_tax' => 0, 'excess_rate' => 0.15],
            ['min_compensation' => 16667, 'max_compensation' => 33333, 'base_tax' => 937.50, 'excess_rate' => 0.20],
            ['min_compensation' => 33333, 'max_compensation' => 83333, 'base_tax' => 4270.83, 'excess_rate' => 0.25],
            ['min_compensation' => 83333, 'max_compensation' => 333333, 'base_tax' => 16770.83, 'excess_rate' => 0.30],
            ['min_compensation' => 333333, 'max_compensation' => null, 'base_tax' => 91770.83, 'excess_rate' => 0.35],
        ];
    }

    /**
     * Get the TRAIN Law weekly withholding tax brackets (2023 onwards).
     *
     * @return array<array<string, mixed>>
     */
    protected function getWeeklyBrackets(): array
    {
        return [
            ['min_compensation' => 0, 'max_compensation' => 4808, 'base_tax' => 0, 'excess_rate' => 0],
            ['min_compensation' => 4808, 'max_compensation' => 7692, 'base_tax' => 0, 'excess_rate' => 0.15],
            ['min_compensation' => 7692, 'max_compensation' => 15385, 'base_tax' => 432.69, 'excess_rate' => 0.20],
            ['min_compensation' => 15385, 'max_compensation' => 38462, 'base_tax' => 1971.15, 'excess_rate' => 0.25],
            ['min_compensation' => 38462, 'max_compensation' => 153846, 'base_tax' => 7740.38, 'excess_rate' => 0.30],
            ['min_compensation' => 153846, 'max_compensation' => null, 'base_tax' => 42355.77, 'excess_rate' => 0.35],
        ];
    }

    /**
     * Get the TRAIN Law daily withholding tax brackets (2023 onwards).
     *
     * @return array<array<string, mixed>>
     */
    protected function getDailyBrackets(): array
    {
        return [
            ['min_compensation' => 0, 'max_compensation' => 685, 'base_tax' => 0, 'excess_rate' => 0],
            ['min_compensation' => 685, 'max_compensation' => 1096, 'base_tax' => 0, 'excess_rate' => 0.15],
            ['min_compensation' => 1096, 'max_compensation' => 2192, 'base_tax' => 61.65, 'excess_rate' => 0.20],
            ['min_compensation' => 2192, 'max_compensation' => 5479, 'base_tax' => 280.85, 'excess_rate' => 0.25],
            ['min_compensation' => 5479, 'max_compensation' => 21918, 'base_tax' => 1102.60, 'excess_rate' => 0.30],
            ['min_compensation' => 21918, 'max_compensation' => null, 'base_tax' => 6034.30, 'excess_rate' => 0.35],
        ];
    }
}
