<?php

namespace Database\Factories;

use App\Enums\BankAccountType;
use App\Enums\PayType;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployeeCompensation>
 */
class EmployeeCompensationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = EmployeeCompensation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'basic_pay' => fake()->randomFloat(2, 15000, 200000),
            'currency' => 'PHP',
            'pay_type' => fake()->randomElement(PayType::cases()),
            'effective_date' => fake()->dateTimeBetween('-2 years', 'now'),
            'bank_name' => fake()->optional(0.8)->randomElement([
                'BDO',
                'BPI',
                'Metrobank',
                'Landbank',
                'PNB',
                'Security Bank',
                'UnionBank',
                'RCBC',
            ]),
            'account_name' => fake()->optional(0.8)->name(),
            'account_number' => fake()->optional(0.8)->numerify('##########'),
            'account_type' => fake()->optional(0.8)->randomElement(BankAccountType::cases()),
        ];
    }

    /**
     * Indicate that this is a monthly paid compensation.
     */
    public function monthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_type' => PayType::Monthly,
        ]);
    }

    /**
     * Indicate that this is a semi-monthly paid compensation.
     */
    public function semiMonthly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_type' => PayType::SemiMonthly,
        ]);
    }

    /**
     * Indicate that this is a weekly paid compensation.
     */
    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_type' => PayType::Weekly,
        ]);
    }

    /**
     * Indicate that this is a daily paid compensation.
     */
    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'pay_type' => PayType::Daily,
        ]);
    }

    /**
     * Indicate that this compensation has bank account details.
     */
    public function withBankAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_name' => fake()->randomElement([
                'BDO',
                'BPI',
                'Metrobank',
                'Landbank',
                'PNB',
            ]),
            'account_name' => fake()->name(),
            'account_number' => fake()->numerify('##########'),
            'account_type' => fake()->randomElement(BankAccountType::cases()),
        ]);
    }

    /**
     * Indicate that this compensation has no bank account details.
     */
    public function withoutBankAccount(): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_name' => null,
            'account_name' => null,
            'account_number' => null,
            'account_type' => null,
        ]);
    }

    /**
     * Set a specific basic pay amount.
     */
    public function withBasicPay(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'basic_pay' => $amount,
        ]);
    }
}
