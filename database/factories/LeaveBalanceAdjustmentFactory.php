<?php

namespace Database\Factories;

use App\Enums\LeaveBalanceAdjustmentType;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LeaveBalanceAdjustment>
 */
class LeaveBalanceAdjustmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = LeaveBalanceAdjustment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $previousBalance = fake()->randomFloat(2, 0, 10);
        $days = fake()->randomFloat(2, 0.5, 5);
        $type = fake()->randomElement(LeaveBalanceAdjustmentType::cases());
        $newBalance = $previousBalance + ($days * $type->sign());

        return [
            'leave_balance_id' => LeaveBalance::factory(),
            'adjusted_by' => User::factory(),
            'adjustment_type' => $type,
            'days' => $days,
            'reason' => fake()->sentence(10),
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'reference_type' => null,
            'reference_id' => null,
        ];
    }

    /**
     * Create a credit adjustment.
     */
    public function credit(?float $days = null): static
    {
        return $this->state(function (array $attributes) use ($days) {
            $adjustmentDays = $days ?? fake()->randomFloat(2, 0.5, 5);
            $newBalance = (float) $attributes['previous_balance'] + $adjustmentDays;

            return [
                'adjustment_type' => LeaveBalanceAdjustmentType::Credit,
                'days' => $adjustmentDays,
                'new_balance' => $newBalance,
            ];
        });
    }

    /**
     * Create a debit adjustment.
     */
    public function debit(?float $days = null): static
    {
        return $this->state(function (array $attributes) use ($days) {
            $adjustmentDays = $days ?? fake()->randomFloat(2, 0.5, 5);
            $newBalance = (float) $attributes['previous_balance'] - $adjustmentDays;

            return [
                'adjustment_type' => LeaveBalanceAdjustmentType::Debit,
                'days' => $adjustmentDays,
                'new_balance' => $newBalance,
            ];
        });
    }

    /**
     * Set the previous and new balance explicitly.
     */
    public function withBalances(float $previous, float $new): static
    {
        return $this->state(fn (array $attributes) => [
            'previous_balance' => $previous,
            'new_balance' => $new,
        ]);
    }

    /**
     * Add a reference.
     */
    public function withReference(string $type, int $id): static
    {
        return $this->state(fn (array $attributes) => [
            'reference_type' => $type,
            'reference_id' => $id,
        ]);
    }
}
