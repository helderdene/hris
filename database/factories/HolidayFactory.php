<?php

namespace Database\Factories;

use App\Enums\HolidayType;
use App\Models\Holiday;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Holiday>
 */
class HolidayFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Holiday::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('now', '+1 year');

        return [
            'name' => fake()->randomElement([
                'New Year\'s Day',
                'Independence Day',
                'Christmas Day',
                'Labor Day',
                'Company Anniversary',
            ]),
            'date' => $date,
            'holiday_type' => fake()->randomElement(HolidayType::cases()),
            'description' => fake()->optional()->sentence(),
            'is_national' => true,
            'year' => (int) $date->format('Y'),
            'work_location_id' => null,
        ];
    }

    /**
     * Indicate that this is a national holiday.
     */
    public function national(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_national' => true,
            'work_location_id' => null,
        ]);
    }

    /**
     * Indicate that this is a local/regional holiday for a specific location.
     */
    public function local(?WorkLocation $location = null): static
    {
        return $this->state(fn (array $attributes) => [
            'is_national' => false,
            'work_location_id' => $location?->id ?? WorkLocation::factory(),
        ]);
    }

    /**
     * Create a regular holiday (200% pay).
     */
    public function regular(): static
    {
        return $this->state(fn (array $attributes) => [
            'holiday_type' => HolidayType::Regular,
        ]);
    }

    /**
     * Create a special non-working day (130% pay).
     */
    public function specialNonWorking(): static
    {
        return $this->state(fn (array $attributes) => [
            'holiday_type' => HolidayType::SpecialNonWorking,
        ]);
    }

    /**
     * Create a special working day (100% pay).
     */
    public function specialWorking(): static
    {
        return $this->state(fn (array $attributes) => [
            'holiday_type' => HolidayType::SpecialWorking,
        ]);
    }

    /**
     * Create a double holiday (configurable pay).
     */
    public function double(): static
    {
        return $this->state(fn (array $attributes) => [
            'holiday_type' => HolidayType::Double,
        ]);
    }

    /**
     * Set a specific date for the holiday.
     */
    public function forDate(string $date): static
    {
        $parsedDate = \Carbon\Carbon::parse($date);

        return $this->state(fn (array $attributes) => [
            'date' => $parsedDate,
            'year' => $parsedDate->year,
        ]);
    }

    /**
     * Set a specific year for the holiday.
     */
    public function forYear(int $year): static
    {
        return $this->state(function (array $attributes) use ($year) {
            $currentDate = \Carbon\Carbon::parse($attributes['date']);

            return [
                'date' => $currentDate->year($year),
                'year' => $year,
            ];
        });
    }
}
