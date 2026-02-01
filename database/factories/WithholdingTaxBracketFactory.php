<?php

namespace Database\Factories;

use App\Models\WithholdingTaxBracket;
use App\Models\WithholdingTaxTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WithholdingTaxBracket>
 */
class WithholdingTaxBracketFactory extends Factory
{
    protected $model = WithholdingTaxBracket::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minCompensation = fake()->numberBetween(10000, 50000);
        $maxCompensation = $minCompensation + fake()->numberBetween(10000, 20000);

        return [
            'withholding_tax_table_id' => WithholdingTaxTable::factory(),
            'min_compensation' => $minCompensation,
            'max_compensation' => $maxCompensation,
            'base_tax' => fake()->randomFloat(2, 0, 5000),
            'excess_rate' => fake()->randomElement([0, 0.15, 0.20, 0.25, 0.30, 0.35]),
        ];
    }

    /**
     * Configure the bracket for a specific table.
     */
    public function forTable(WithholdingTaxTable $table): static
    {
        return $this->state(fn (array $attributes) => [
            'withholding_tax_table_id' => $table->id,
        ]);
    }

    /**
     * Configure the bracket with no upper limit (highest bracket).
     */
    public function unlimited(): static
    {
        return $this->state(fn (array $attributes) => [
            'max_compensation' => null,
        ]);
    }

    /**
     * Configure the bracket as tax-exempt (0% rate).
     */
    public function exempt(): static
    {
        return $this->state(fn (array $attributes) => [
            'base_tax' => 0,
            'excess_rate' => 0,
        ]);
    }
}
