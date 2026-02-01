<?php

namespace Database\Factories;

use App\Models\AdjustmentApplication;
use App\Models\EmployeeAdjustment;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AdjustmentApplication>
 */
class AdjustmentApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = AdjustmentApplication::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_adjustment_id' => EmployeeAdjustment::factory(),
            'payroll_period_id' => PayrollPeriod::factory(),
            'payroll_entry_id' => null,
            'amount' => fake()->randomFloat(2, 500, 10000),
            'balance_before' => null,
            'balance_after' => null,
            'applied_at' => fake()->dateTimeBetween('-1 month', 'now'),
            'status' => 'applied',
        ];
    }

    /**
     * Create an application with balance tracking.
     */
    public function withBalanceTracking(float $balanceBefore, float $amount): static
    {
        $balanceAfter = max(0, $balanceBefore - $amount);

        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
        ]);
    }

    /**
     * Create a pending application.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'applied_at' => null,
        ]);
    }

    /**
     * Create an applied application.
     */
    public function applied(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'applied',
            'applied_at' => now(),
        ]);
    }

    /**
     * Create an application for a specific adjustment.
     */
    public function forAdjustment(EmployeeAdjustment|int $adjustment): static
    {
        $adjustmentId = $adjustment instanceof EmployeeAdjustment ? $adjustment->id : $adjustment;

        return $this->state(fn (array $attributes) => [
            'employee_adjustment_id' => $adjustmentId,
        ]);
    }

    /**
     * Create an application for a specific period.
     */
    public function forPeriod(PayrollPeriod|int $period): static
    {
        $periodId = $period instanceof PayrollPeriod ? $period->id : $period;

        return $this->state(fn (array $attributes) => [
            'payroll_period_id' => $periodId,
        ]);
    }

    /**
     * Create an application linked to a payroll entry.
     */
    public function forEntry(PayrollEntry|int $entry): static
    {
        $entryId = $entry instanceof PayrollEntry ? $entry->id : $entry;

        return $this->state(fn (array $attributes) => [
            'payroll_entry_id' => $entryId,
        ]);
    }
}
