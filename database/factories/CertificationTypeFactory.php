<?php

namespace Database\Factories;

use App\Models\CertificationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificationType>
 */
class CertificationTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CertificationType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement([
                'PRC License',
                'TESDA Certification',
                'First Aid Training',
                'Fire Safety Certificate',
                'Food Handler Certificate',
                'Security Training',
                'Driver License',
                'Heavy Equipment Operator License',
            ]),
            'description' => fake()->optional()->sentence(),
            'validity_period_months' => fake()->randomElement([12, 24, 36, 60, null]),
            'reminder_days_before_expiry' => [90, 60, 30],
            'is_mandatory' => fake()->boolean(20),
            'is_active' => true,
        ];
    }

    /**
     * Indicate that this is a mandatory certification type.
     */
    public function mandatory(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_mandatory' => true,
        ]);
    }

    /**
     * Indicate that this certification type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific validity period.
     */
    public function validFor(int $months): static
    {
        return $this->state(fn (array $attributes) => [
            'validity_period_months' => $months,
        ]);
    }

    /**
     * Set custom reminder days.
     *
     * @param  array<int>  $days
     */
    public function withReminderDays(array $days): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_days_before_expiry' => $days,
        ]);
    }

    /**
     * Set no expiry (perpetual certification).
     */
    public function noExpiry(): static
    {
        return $this->state(fn (array $attributes) => [
            'validity_period_months' => null,
            'reminder_days_before_expiry' => null,
        ]);
    }

    /**
     * Create a PRC Professional License type.
     */
    public function prcLicense(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'PRC Professional License',
            'description' => 'Professional Regulation Commission license for regulated professions',
            'validity_period_months' => 36,
            'reminder_days_before_expiry' => [90, 60, 30],
            'is_mandatory' => true,
        ]);
    }

    /**
     * Create a TESDA certification type.
     */
    public function tesdaCertification(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'TESDA National Certificate',
            'description' => 'Technical Education and Skills Development Authority certification',
            'validity_period_months' => 60,
            'reminder_days_before_expiry' => [90, 60, 30],
            'is_mandatory' => false,
        ]);
    }
}
