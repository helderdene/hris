<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveBalance>
 */
class LeaveBalanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = LeaveBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'leave_type_id' => LeaveType::factory(),
            'year' => now()->year,
            'brought_forward' => 0,
            'earned' => fake()->randomFloat(2, 5, 15),
            'used' => 0,
            'pending' => 0,
            'adjustments' => 0,
            'expired' => 0,
            'carry_over_expiry_date' => null,
            'last_accrual_at' => null,
            'year_end_processed_at' => null,
        ];
    }

    /**
     * Set the year for the balance.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => $year,
        ]);
    }

    /**
     * Set initial earned balance.
     */
    public function withEarned(float $days): static
    {
        return $this->state(fn (array $attributes) => [
            'earned' => $days,
        ]);
    }

    /**
     * Set carried forward balance.
     */
    public function withCarryOver(float $days, ?string $expiryDate = null): static
    {
        return $this->state(fn (array $attributes) => [
            'brought_forward' => $days,
            'carry_over_expiry_date' => $expiryDate,
        ]);
    }

    /**
     * Set used balance.
     */
    public function withUsed(float $days): static
    {
        return $this->state(fn (array $attributes) => [
            'used' => $days,
        ]);
    }

    /**
     * Set pending balance.
     */
    public function withPending(float $days): static
    {
        return $this->state(fn (array $attributes) => [
            'pending' => $days,
        ]);
    }

    /**
     * Set adjustments.
     */
    public function withAdjustments(float $days): static
    {
        return $this->state(fn (array $attributes) => [
            'adjustments' => $days,
        ]);
    }

    /**
     * Mark as year-end processed.
     */
    public function yearEndProcessed(): static
    {
        return $this->state(fn (array $attributes) => [
            'year_end_processed_at' => now(),
        ]);
    }

    /**
     * Mark as having monthly accrual.
     */
    public function withMonthlyAccrual(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_accrual_at' => now(),
        ]);
    }
}
