<?php

use App\Enums\HolidayType;
use App\Models\Holiday;
use App\Models\WorkLocation;
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

describe('Holiday Model', function () {
    it('casts holiday_type to HolidayType enum and retrieves label', function () {
        $holiday = Holiday::factory()->regular()->create([
            'name' => 'Independence Day',
        ]);

        // Test enum casting
        expect($holiday->holiday_type)->toBeInstanceOf(HolidayType::class);
        expect($holiday->holiday_type)->toBe(HolidayType::Regular);

        // Test label retrieval
        expect($holiday->holiday_type->label())->toBe('Regular Holiday');

        // Test all holiday type labels
        expect(HolidayType::Regular->label())->toBe('Regular Holiday');
        expect(HolidayType::SpecialNonWorking->label())->toBe('Special Non-Working Day');
        expect(HolidayType::SpecialWorking->label())->toBe('Special Working Day');
        expect(HolidayType::Double->label())->toBe('Double Holiday');

        // Test premium rates
        expect(HolidayType::Regular->premiumRate())->toBe(200);
        expect(HolidayType::SpecialNonWorking->premiumRate())->toBe(130);
        expect(HolidayType::SpecialWorking->premiumRate())->toBe(100);
        expect(HolidayType::Double->premiumRate())->toBe(300);
        expect(HolidayType::Double->premiumRate(350))->toBe(350);
    });

    it('scopes holidays by is_national boolean', function () {
        // Create national holidays
        Holiday::factory()->national()->count(3)->create();

        // Create local holidays
        $location = WorkLocation::factory()->create();
        Holiday::factory()->local($location)->count(2)->create();

        // Test national scope
        $nationalHolidays = Holiday::national()->get();
        expect($nationalHolidays)->toHaveCount(3);
        expect($nationalHolidays->every(fn ($h) => $h->is_national === true))->toBeTrue();

        // Test that all holidays can be retrieved
        $allHolidays = Holiday::all();
        expect($allHolidays)->toHaveCount(5);
    });

    it('associates with work_location for regional holidays', function () {
        $location = WorkLocation::factory()->create([
            'name' => 'Cebu Branch Office',
            'code' => 'CEBU-01',
        ]);

        $localHoliday = Holiday::factory()->local($location)->create([
            'name' => 'Sinulog Festival',
        ]);

        // Test relationship exists
        expect($localHoliday->workLocation)->toBeInstanceOf(WorkLocation::class);
        expect($localHoliday->workLocation->id)->toBe($location->id);
        expect($localHoliday->workLocation->name)->toBe('Cebu Branch Office');

        // Test is_national is false for local holidays
        expect($localHoliday->is_national)->toBeFalse();

        // National holidays should not have work_location
        $nationalHoliday = Holiday::factory()->national()->create([
            'name' => 'Independence Day',
        ]);

        expect($nationalHoliday->workLocation)->toBeNull();
        expect($nationalHoliday->is_national)->toBeTrue();
    });

    it('scopes holidays by year and date range', function () {
        // Create holidays for 2026
        Holiday::factory()->forDate('2026-01-01')->create(['name' => 'New Year 2026']);
        Holiday::factory()->forDate('2026-06-12')->create(['name' => 'Independence Day 2026']);
        Holiday::factory()->forDate('2026-12-25')->create(['name' => 'Christmas 2026']);

        // Create holidays for 2027
        Holiday::factory()->forDate('2027-01-01')->create(['name' => 'New Year 2027']);
        Holiday::factory()->forDate('2027-06-12')->create(['name' => 'Independence Day 2027']);

        // Test forYear scope
        $holidays2026 = Holiday::forYear(2026)->get();
        expect($holidays2026)->toHaveCount(3);
        expect($holidays2026->every(fn ($h) => $h->year === 2026))->toBeTrue();

        $holidays2027 = Holiday::forYear(2027)->get();
        expect($holidays2027)->toHaveCount(2);
        expect($holidays2027->every(fn ($h) => $h->year === 2027))->toBeTrue();

        // Test forDateRange scope
        $q1Holidays = Holiday::forDateRange('2026-01-01', '2026-03-31')->get();
        expect($q1Holidays)->toHaveCount(1);
        expect($q1Holidays->first()->name)->toBe('New Year 2026');

        $q2Holidays = Holiday::forDateRange('2026-04-01', '2026-06-30')->get();
        expect($q2Holidays)->toHaveCount(1);
        expect($q2Holidays->first()->name)->toBe('Independence Day 2026');
    });

    it('supports soft delete functionality', function () {
        $holiday = Holiday::factory()->create([
            'name' => 'Temporary Holiday',
        ]);

        $holidayId = $holiday->id;

        // Verify holiday exists
        expect(Holiday::find($holidayId))->not->toBeNull();

        // Soft delete the holiday
        $holiday->delete();

        // Verify holiday is not retrieved by default query
        expect(Holiday::find($holidayId))->toBeNull();

        // Verify holiday can be retrieved with trashed
        $trashedHoliday = Holiday::withTrashed()->find($holidayId);
        expect($trashedHoliday)->not->toBeNull();
        expect($trashedHoliday->deleted_at)->not->toBeNull();

        // Verify holiday can be restored
        $trashedHoliday->restore();
        expect(Holiday::find($holidayId))->not->toBeNull();
        expect(Holiday::find($holidayId)->deleted_at)->toBeNull();
    });

    it('scopes holidays by work location', function () {
        $location1 = WorkLocation::factory()->create(['name' => 'Manila Office']);
        $location2 = WorkLocation::factory()->create(['name' => 'Cebu Office']);

        // Create holidays for specific locations
        Holiday::factory()->local($location1)->count(2)->create();
        Holiday::factory()->local($location2)->count(3)->create();

        // Create national holidays
        Holiday::factory()->national()->count(2)->create();

        // Test forLocation scope
        $manilaHolidays = Holiday::forLocation($location1->id)->get();
        expect($manilaHolidays)->toHaveCount(2);
        expect($manilaHolidays->every(fn ($h) => $h->work_location_id === $location1->id))->toBeTrue();

        $cebuHolidays = Holiday::forLocation($location2->id)->get();
        expect($cebuHolidays)->toHaveCount(3);
        expect($cebuHolidays->every(fn ($h) => $h->work_location_id === $location2->id))->toBeTrue();
    });
});
