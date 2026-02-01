<?php

namespace Database\Seeders;

use App\Enums\HolidayType;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeder for Philippine national holidays.
 *
 * Seeds the standard Philippine national holidays for the current year and next year.
 * Includes both fixed-date holidays and placeholders for movable holidays.
 * Movable holidays (Eid, Chinese New Year, Holy Week) have dates set to null
 * as they must be manually updated by HR each year based on official proclamations.
 */
class PhilippineHolidaySeeder extends Seeder
{
    /**
     * Fixed-date Philippine national holidays.
     *
     * These holidays occur on the same date every year.
     *
     * @var array<int, array{name: string, month: int, day: int, type: HolidayType, description: string}>
     */
    protected array $fixedHolidays = [
        [
            'name' => 'New Year\'s Day',
            'month' => 1,
            'day' => 1,
            'type' => HolidayType::Regular,
            'description' => 'Celebration of the New Year',
        ],
        [
            'name' => 'Araw ng Kagitingan',
            'month' => 4,
            'day' => 9,
            'type' => HolidayType::Regular,
            'description' => 'Day of Valor - commemoration of the Fall of Bataan',
        ],
        [
            'name' => 'Labor Day',
            'month' => 5,
            'day' => 1,
            'type' => HolidayType::Regular,
            'description' => 'International Workers\' Day',
        ],
        [
            'name' => 'Independence Day',
            'month' => 6,
            'day' => 12,
            'type' => HolidayType::Regular,
            'description' => 'Philippine Declaration of Independence in 1898',
        ],
        [
            'name' => 'Bonifacio Day',
            'month' => 11,
            'day' => 30,
            'type' => HolidayType::Regular,
            'description' => 'Birth anniversary of Andres Bonifacio',
        ],
        [
            'name' => 'Christmas Day',
            'month' => 12,
            'day' => 25,
            'type' => HolidayType::Regular,
            'description' => 'Celebration of Christmas',
        ],
        [
            'name' => 'Rizal Day',
            'month' => 12,
            'day' => 30,
            'type' => HolidayType::Regular,
            'description' => 'Death anniversary of Jose Rizal',
        ],
    ];

    /**
     * Movable Philippine holidays.
     *
     * These holidays do not have fixed dates and must be updated manually by HR
     * based on official government proclamations each year.
     *
     * @var array<int, array{name: string, type: HolidayType, description: string}>
     */
    protected array $movableHolidays = [
        [
            'name' => 'Maundy Thursday',
            'type' => HolidayType::Regular,
            'description' => 'Thursday before Easter Sunday - date varies yearly',
        ],
        [
            'name' => 'Good Friday',
            'type' => HolidayType::Regular,
            'description' => 'Friday before Easter Sunday - date varies yearly',
        ],
        [
            'name' => 'Eid\'l Fitr',
            'type' => HolidayType::Regular,
            'description' => 'End of Ramadan - date based on Islamic calendar',
        ],
        [
            'name' => 'Eid\'l Adha',
            'type' => HolidayType::Regular,
            'description' => 'Feast of Sacrifice - date based on Islamic calendar',
        ],
        [
            'name' => 'Chinese New Year',
            'type' => HolidayType::SpecialNonWorking,
            'description' => 'Lunar New Year - date based on Chinese calendar',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currentYear = Carbon::now()->year;
        $nextYear = $currentYear + 1;

        $this->seedHolidaysForYear($currentYear);
        $this->seedHolidaysForYear($nextYear);
    }

    /**
     * Seed all holidays for a specific year.
     */
    protected function seedHolidaysForYear(int $year): void
    {
        $this->seedFixedHolidays($year);
        $this->seedNationalHeroesDay($year);
        $this->seedMovableHolidays($year);
    }

    /**
     * Seed fixed-date holidays for a specific year.
     */
    protected function seedFixedHolidays(int $year): void
    {
        foreach ($this->fixedHolidays as $holiday) {
            $date = Carbon::create($year, $holiday['month'], $holiday['day']);

            Holiday::firstOrCreate(
                [
                    'name' => $holiday['name'],
                    'year' => $year,
                ],
                [
                    'date' => $date,
                    'holiday_type' => $holiday['type'],
                    'description' => $holiday['description'],
                    'is_national' => true,
                    'work_location_id' => null,
                ]
            );
        }
    }

    /**
     * Seed National Heroes Day for a specific year.
     *
     * National Heroes Day falls on the last Monday of August.
     */
    protected function seedNationalHeroesDay(int $year): void
    {
        $date = $this->getLastMondayOfMonth($year, 8);

        Holiday::firstOrCreate(
            [
                'name' => 'National Heroes Day',
                'year' => $year,
            ],
            [
                'date' => $date,
                'holiday_type' => HolidayType::Regular,
                'description' => 'Commemoration of Philippine heroes',
                'is_national' => true,
                'work_location_id' => null,
            ]
        );
    }

    /**
     * Seed movable holidays as placeholders for a specific year.
     *
     * Movable holidays are created with January 1 as a placeholder date.
     * HR must update the actual dates based on official proclamations.
     */
    protected function seedMovableHolidays(int $year): void
    {
        foreach ($this->movableHolidays as $holiday) {
            Holiday::firstOrCreate(
                [
                    'name' => $holiday['name'],
                    'year' => $year,
                ],
                [
                    'date' => Carbon::create($year, 1, 1),
                    'holiday_type' => $holiday['type'],
                    'description' => $holiday['description'],
                    'is_national' => true,
                    'work_location_id' => null,
                ]
            );
        }
    }

    /**
     * Get the last Monday of a given month.
     */
    protected function getLastMondayOfMonth(int $year, int $month): Carbon
    {
        return Carbon::create($year, $month, 1)
            ->endOfMonth()
            ->modify('last monday');
    }

    /**
     * Get the count of fixed-date holidays.
     */
    public function getFixedHolidaysCount(): int
    {
        return count($this->fixedHolidays);
    }

    /**
     * Get the count of movable holidays.
     */
    public function getMovableHolidaysCount(): int
    {
        return count($this->movableHolidays);
    }

    /**
     * Get total holidays count per year (fixed + National Heroes Day + movable).
     */
    public function getTotalHolidaysPerYear(): int
    {
        return $this->getFixedHolidaysCount() + 1 + $this->getMovableHolidaysCount();
    }

    /**
     * Get the array of fixed holiday names.
     *
     * @return array<string>
     */
    public function getFixedHolidayNames(): array
    {
        return array_map(fn ($h) => $h['name'], $this->fixedHolidays);
    }

    /**
     * Get the array of movable holiday names.
     *
     * @return array<string>
     */
    public function getMovableHolidayNames(): array
    {
        return array_map(fn ($h) => $h['name'], $this->movableHolidays);
    }
}
