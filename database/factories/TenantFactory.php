<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $companyName = fake()->company();

        return [
            'name' => $companyName,
            'slug' => Str::slug($companyName).'-'.fake()->unique()->numberBetween(1, 9999),
            'logo_path' => null,
            'primary_color' => fake()->hexColor(),
            'timezone' => 'Asia/Manila',
            'business_info' => [
                'company_name' => $companyName,
                'address' => fake()->address(),
                'tin' => fake()->numerify('###-###-###-###'),
            ],
            'payroll_settings' => [
                'pay_frequency' => fake()->randomElement(['weekly', 'semi-monthly', 'monthly']),
                'cutoff_day' => fake()->numberBetween(1, 28),
                'double_holiday_rate' => 300,
            ],
            'leave_defaults' => [
                'vacation_days' => fake()->numberBetween(10, 20),
                'sick_days' => fake()->numberBetween(5, 15),
            ],
        ];
    }

    /**
     * Indicate that the tenant has no branding set.
     */
    public function withoutBranding(): static
    {
        return $this->state(fn (array $attributes) => [
            'logo_path' => null,
            'primary_color' => null,
        ]);
    }

    /**
     * Indicate a minimal tenant with only required fields.
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'logo_path' => null,
            'primary_color' => null,
            'business_info' => null,
            'payroll_settings' => null,
            'leave_defaults' => null,
        ]);
    }
}
