<?php

namespace Database\Factories;

use App\Enums\EarningType;
use App\Models\PayrollEarning;
use App\Models\PayrollEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PayrollEarning>
 */
class PayrollEarningFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = PayrollEarning::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(EarningType::cases());
        $quantity = fake()->randomFloat(4, 1, 100);
        $rate = fake()->randomFloat(4, 50, 500);
        $multiplier = 1.00;
        $amount = $quantity * $rate * $multiplier;

        return [
            'payroll_entry_id' => PayrollEntry::factory(),
            'earning_type' => $type,
            'earning_code' => strtoupper(fake()->lexify('???-###')),
            'description' => $type->label(),
            'quantity' => $quantity,
            'quantity_unit' => fake()->randomElement(['hours', 'days', 'minutes', null]),
            'rate' => $rate,
            'multiplier' => $multiplier,
            'amount' => $amount,
            'is_taxable' => $type->isTaxableByDefault(),
            'remarks' => null,
        ];
    }

    /**
     * Create a basic pay earning.
     */
    public function basicPay(?float $amount = null): static
    {
        return $this->state(fn (array $attributes) => [
            'earning_type' => EarningType::BasicPay,
            'earning_code' => 'BASIC',
            'description' => 'Basic Pay',
            'quantity' => 1,
            'quantity_unit' => 'month',
            'rate' => $amount ?? fake()->randomFloat(2, 15000, 80000),
            'multiplier' => 1.00,
            'amount' => $amount ?? $attributes['rate'],
            'is_taxable' => true,
        ]);
    }

    /**
     * Create an overtime earning.
     */
    public function overtime(?float $hours = null, ?float $rate = null, float $multiplier = 1.25): static
    {
        $hrs = $hours ?? fake()->randomFloat(2, 1, 40);
        $rt = $rate ?? fake()->randomFloat(4, 50, 200);

        return $this->state(fn (array $attributes) => [
            'earning_type' => EarningType::Overtime,
            'earning_code' => 'OT',
            'description' => 'Overtime Pay',
            'quantity' => $hrs,
            'quantity_unit' => 'hours',
            'rate' => $rt,
            'multiplier' => $multiplier,
            'amount' => round($hrs * $rt * $multiplier, 2),
            'is_taxable' => true,
        ]);
    }

    /**
     * Create a night differential earning.
     */
    public function nightDifferential(?float $hours = null, ?float $rate = null): static
    {
        $hrs = $hours ?? fake()->randomFloat(2, 1, 20);
        $rt = $rate ?? fake()->randomFloat(4, 10, 50);

        return $this->state(fn (array $attributes) => [
            'earning_type' => EarningType::NightDifferential,
            'earning_code' => 'ND',
            'description' => 'Night Differential',
            'quantity' => $hrs,
            'quantity_unit' => 'hours',
            'rate' => $rt,
            'multiplier' => 1.00,
            'amount' => round($hrs * $rt, 2),
            'is_taxable' => true,
        ]);
    }

    /**
     * Create a holiday pay earning.
     */
    public function holidayPay(?float $days = null, ?float $rate = null, float $multiplier = 2.00): static
    {
        $d = $days ?? fake()->randomFloat(2, 1, 3);
        $rt = $rate ?? fake()->randomFloat(2, 500, 2000);

        return $this->state(fn (array $attributes) => [
            'earning_type' => EarningType::HolidayPay,
            'earning_code' => 'HOLIDAY',
            'description' => 'Holiday Pay',
            'quantity' => $d,
            'quantity_unit' => 'days',
            'rate' => $rt,
            'multiplier' => $multiplier,
            'amount' => round($d * $rt * $multiplier, 2),
            'is_taxable' => true,
        ]);
    }

    /**
     * Create an allowance earning.
     */
    public function allowance(?string $name = null, ?float $amount = null): static
    {
        $amt = $amount ?? fake()->randomFloat(2, 500, 5000);

        return $this->state(fn (array $attributes) => [
            'earning_type' => EarningType::Allowance,
            'earning_code' => 'ALLOW',
            'description' => $name ?? 'Allowance',
            'quantity' => 1,
            'quantity_unit' => null,
            'rate' => $amt,
            'multiplier' => 1.00,
            'amount' => $amt,
            'is_taxable' => false,
        ]);
    }

    /**
     * Create a bonus earning.
     */
    public function bonus(?string $name = null, ?float $amount = null): static
    {
        $amt = $amount ?? fake()->randomFloat(2, 1000, 10000);

        return $this->state(fn (array $attributes) => [
            'earning_type' => EarningType::Bonus,
            'earning_code' => 'BONUS',
            'description' => $name ?? 'Bonus',
            'quantity' => 1,
            'quantity_unit' => null,
            'rate' => $amt,
            'multiplier' => 1.00,
            'amount' => $amt,
            'is_taxable' => true,
        ]);
    }

    /**
     * Set a specific payroll entry for the earning.
     */
    public function forEntry(PayrollEntry $entry): static
    {
        return $this->state(fn (array $attributes) => [
            'payroll_entry_id' => $entry->id,
        ]);
    }
}
