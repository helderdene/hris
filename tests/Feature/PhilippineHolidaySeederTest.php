<?php

use App\Enums\HolidayType;
use App\Models\Holiday;
use Carbon\Carbon;
use Database\Seeders\PhilippineHolidaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run tenant-specific migrations for testing
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('Philippine Holiday Seeder', function () {
    it('creates correct number of holidays for current and next year', function () {
        $seeder = new PhilippineHolidaySeeder;
        $seeder->run();

        $currentYear = Carbon::now()->year;
        $nextYear = $currentYear + 1;

        // Expected: 7 fixed + 1 National Heroes Day + 5 movable = 13 per year
        $expectedPerYear = $seeder->getTotalHolidaysPerYear();

        $currentYearHolidays = Holiday::forYear($currentYear)->count();
        $nextYearHolidays = Holiday::forYear($nextYear)->count();
        $totalHolidays = Holiday::count();

        expect($expectedPerYear)->toBe(13);
        expect($currentYearHolidays)->toBe($expectedPerYear);
        expect($nextYearHolidays)->toBe($expectedPerYear);
        expect($totalHolidays)->toBe($expectedPerYear * 2);
    });

    it('includes all fixed-date national holidays', function () {
        $seeder = new PhilippineHolidaySeeder;
        $seeder->run();

        $currentYear = Carbon::now()->year;

        // Check all fixed holidays exist
        $fixedHolidays = [
            ['name' => 'New Year\'s Day', 'month' => 1, 'day' => 1],
            ['name' => 'Araw ng Kagitingan', 'month' => 4, 'day' => 9],
            ['name' => 'Labor Day', 'month' => 5, 'day' => 1],
            ['name' => 'Independence Day', 'month' => 6, 'day' => 12],
            ['name' => 'Bonifacio Day', 'month' => 11, 'day' => 30],
            ['name' => 'Christmas Day', 'month' => 12, 'day' => 25],
            ['name' => 'Rizal Day', 'month' => 12, 'day' => 30],
        ];

        foreach ($fixedHolidays as $holiday) {
            $dbHoliday = Holiday::where('name', $holiday['name'])
                ->where('year', $currentYear)
                ->first();

            expect($dbHoliday)->not->toBeNull();
            expect($dbHoliday->date->month)->toBe($holiday['month']);
            expect($dbHoliday->date->day)->toBe($holiday['day']);
            expect($dbHoliday->holiday_type)->toBe(HolidayType::Regular);
            expect($dbHoliday->is_national)->toBeTrue();
        }

        // Check National Heroes Day (last Monday of August)
        $heroesDay = Holiday::where('name', 'National Heroes Day')
            ->where('year', $currentYear)
            ->first();

        expect($heroesDay)->not->toBeNull();
        expect($heroesDay->date->month)->toBe(8);
        expect($heroesDay->date->dayOfWeek)->toBe(Carbon::MONDAY);
        expect($heroesDay->holiday_type)->toBe(HolidayType::Regular);
        expect($heroesDay->is_national)->toBeTrue();
    });

    it('creates movable holiday placeholders', function () {
        $seeder = new PhilippineHolidaySeeder;
        $seeder->run();

        $currentYear = Carbon::now()->year;

        $movableHolidays = [
            ['name' => 'Maundy Thursday', 'type' => HolidayType::Regular],
            ['name' => 'Good Friday', 'type' => HolidayType::Regular],
            ['name' => 'Eid\'l Fitr', 'type' => HolidayType::Regular],
            ['name' => 'Eid\'l Adha', 'type' => HolidayType::Regular],
            ['name' => 'Chinese New Year', 'type' => HolidayType::SpecialNonWorking],
        ];

        foreach ($movableHolidays as $holiday) {
            $dbHoliday = Holiday::where('name', $holiday['name'])
                ->where('year', $currentYear)
                ->first();

            expect($dbHoliday)->not->toBeNull();
            expect($dbHoliday->holiday_type)->toBe($holiday['type']);
            expect($dbHoliday->is_national)->toBeTrue();
            expect($dbHoliday->description)->not->toBeNull();
        }

        // Verify movable holidays also exist for next year
        $nextYear = $currentYear + 1;
        foreach ($movableHolidays as $holiday) {
            $nextYearHoliday = Holiday::where('name', $holiday['name'])
                ->where('year', $nextYear)
                ->first();

            expect($nextYearHoliday)->not->toBeNull();
        }
    });
});
