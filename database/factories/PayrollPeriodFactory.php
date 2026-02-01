<?php

namespace Database\Factories;

use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollPeriodType;
use App\Models\PayrollCycle;
use App\Models\PayrollPeriod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollPeriod>
 */
class PayrollPeriodFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PayrollPeriod::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2026);
        $periodNumber = fake()->numberBetween(1, 24);
        $month = (int) ceil($periodNumber / 2);
        $isFirstHalf = $periodNumber % 2 === 1;

        $cutoffStart = Carbon::create($year, $month, $isFirstHalf ? 1 : 16);
        $cutoffEnd = $isFirstHalf
            ? Carbon::create($year, $month, 15)
            : Carbon::create($year, $month, 1)->endOfMonth();
        $payDate = $isFirstHalf
            ? Carbon::create($year, $month, 25)
            : Carbon::create($year, $month, 1)->addMonth()->startOfMonth()->addDays(9);

        return [
            'payroll_cycle_id' => PayrollCycle::factory(),
            'name' => "{$year} Period {$periodNumber}",
            'period_type' => PayrollPeriodType::Regular,
            'year' => $year,
            'period_number' => $periodNumber,
            'cutoff_start' => $cutoffStart,
            'cutoff_end' => $cutoffEnd,
            'pay_date' => $payDate,
            'status' => PayrollPeriodStatus::Draft,
            'employee_count' => 0,
            'total_gross' => 0,
            'total_deductions' => 0,
            'total_net' => 0,
            'opened_at' => null,
            'closed_at' => null,
            'closed_by' => null,
            'notes' => null,
        ];
    }

    /**
     * Create a draft period.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollPeriodStatus::Draft,
            'opened_at' => null,
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }

    /**
     * Create an open period.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollPeriodStatus::Open,
            'opened_at' => now()->subDays(fake()->numberBetween(1, 5)),
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }

    /**
     * Create a processing period.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollPeriodStatus::Processing,
            'opened_at' => now()->subDays(fake()->numberBetween(5, 10)),
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }

    /**
     * Create a closed period.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayrollPeriodStatus::Closed,
            'opened_at' => now()->subDays(fake()->numberBetween(10, 20)),
            'closed_at' => now()->subDays(fake()->numberBetween(1, 5)),
            'employee_count' => fake()->numberBetween(10, 100),
            'total_gross' => fake()->randomFloat(2, 100000, 1000000),
            'total_deductions' => fake()->randomFloat(2, 10000, 100000),
            'total_net' => fake()->randomFloat(2, 90000, 900000),
        ]);
    }

    /**
     * Create a regular period type.
     */
    public function regular(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => PayrollPeriodType::Regular,
        ]);
    }

    /**
     * Create a supplemental period type.
     */
    public function supplemental(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => PayrollPeriodType::Supplemental,
        ]);
    }

    /**
     * Create a 13th month period type.
     */
    public function thirteenthMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => PayrollPeriodType::ThirteenthMonth,
            'period_number' => 1,
        ]);
    }

    /**
     * Create a final pay period type.
     */
    public function finalPay(): static
    {
        return $this->state(fn (array $attributes) => [
            'period_type' => PayrollPeriodType::FinalPay,
        ]);
    }

    /**
     * Set a specific year for the period.
     */
    public function forYear(int $year): static
    {
        return $this->state(fn (array $attributes) => [
            'year' => $year,
            'name' => "{$year} Period ".($attributes['period_number'] ?? 1),
        ]);
    }

    /**
     * Set a specific period number.
     */
    public function periodNumber(int $number): static
    {
        return $this->state(function (array $attributes) use ($number) {
            $year = $attributes['year'] ?? now()->year;

            return [
                'period_number' => $number,
                'name' => "{$year} Period {$number}",
            ];
        });
    }

    /**
     * Set a specific payroll cycle.
     */
    public function forCycle(PayrollCycle $cycle): static
    {
        return $this->state(fn (array $attributes) => [
            'payroll_cycle_id' => $cycle->id,
        ]);
    }

    /**
     * Set specific date range for the period.
     */
    public function withDateRange(Carbon $start, Carbon $end, Carbon $payDate): static
    {
        return $this->state(fn (array $attributes) => [
            'cutoff_start' => $start,
            'cutoff_end' => $end,
            'pay_date' => $payDate,
        ]);
    }

    /**
     * Set financial totals for the period.
     */
    public function withTotals(float $gross, float $deductions, int $employeeCount): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_count' => $employeeCount,
            'total_gross' => $gross,
            'total_deductions' => $deductions,
            'total_net' => $gross - $deductions,
        ]);
    }
}
