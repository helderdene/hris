<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\PlanPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlanPrice>
 */
class PlanPriceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PlanPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'plan_id' => Plan::factory(),
            'billing_interval' => 'monthly',
            'price_per_unit' => 10000,
            'currency' => 'PHP',
            'is_active' => true,
        ];
    }

    /**
     * Configure a monthly price.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_interval' => 'monthly',
        ]);
    }

    /**
     * Configure a yearly price.
     */
    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'billing_interval' => 'yearly',
        ]);
    }
}
