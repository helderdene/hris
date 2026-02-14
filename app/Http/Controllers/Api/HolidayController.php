<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CopyHolidaysRequest;
use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Http\Resources\HolidayResource;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class HolidayController extends Controller
{
    /**
     * Display a listing of holidays.
     *
     * Supports filtering by year, month, and work_location_id.
     * All authenticated users can view holidays (read-only access).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Holiday::query()
            ->with('workLocation')
            ->orderBy('date');

        // Filter by year
        if ($request->filled('year')) {
            $query->forYear((int) $request->input('year'));
        }

        // Filter by month
        if ($request->filled('month')) {
            $query->whereMonth('date', (int) $request->input('month'));
        }

        // Filter by work location
        if ($request->filled('work_location_id')) {
            $locationId = (int) $request->input('work_location_id');
            // Include national holidays and location-specific holidays
            $query->where(function ($q) use ($locationId) {
                $q->where('is_national', true)
                    ->orWhere('work_location_id', $locationId);
            });
        }

        return HolidayResource::collection($query->get());
    }

    /**
     * Store a newly created holiday.
     *
     * Only HR Manager and HR Staff can create holidays.
     */
    public function store(StoreHolidayRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-holidays');

        $data = $request->validated();

        // Extract year from date if not provided
        if (! isset($data['year'])) {
            $data['year'] = Carbon::parse($data['date'])->year;
        }

        $holiday = Holiday::create($data);
        $holiday->load('workLocation');

        return (new HolidayResource($holiday))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified holiday.
     */
    public function show(Holiday $holiday): HolidayResource
    {
        $holiday->load('workLocation');

        return new HolidayResource($holiday);
    }

    /**
     * Update the specified holiday.
     *
     * Only HR Manager and HR Staff can update holidays.
     */
    public function update(UpdateHolidayRequest $request, Holiday $holiday): HolidayResource
    {
        Gate::authorize('can-manage-holidays');

        $data = $request->validated();

        // Update year if date is changed
        if (isset($data['date'])) {
            $data['year'] = Carbon::parse($data['date'])->year;
        }

        $holiday->update($data);
        $holiday->load('workLocation');

        return new HolidayResource($holiday);
    }

    /**
     * Remove the specified holiday (soft delete).
     *
     * Only HR Manager and HR Staff can delete holidays.
     */
    public function destroy(Holiday $holiday): JsonResponse
    {
        Gate::authorize('can-manage-holidays');

        $holiday->delete();

        return response()->json([
            'message' => 'Holiday deleted successfully.',
        ]);
    }

    /**
     * Get holidays formatted for calendar display.
     *
     * Groups holidays by month for calendar rendering.
     * Supports employee context to return relevant holidays (national + their location).
     */
    public function calendar(Request $request): JsonResponse
    {
        $query = Holiday::query()
            ->with('workLocation')
            ->orderBy('date');

        // Filter by year (default to current year)
        $year = $request->input('year', now()->year);
        $query->forYear((int) $year);

        // Support employee context - show national holidays + employee's location holidays
        if ($request->filled('work_location_id')) {
            $locationId = (int) $request->input('work_location_id');
            $query->where(function ($q) use ($locationId) {
                $q->where('is_national', true)
                    ->orWhere('work_location_id', $locationId);
            });
        }

        $holidays = $query->get();

        // Group holidays by month
        $groupedByMonth = $holidays->groupBy(function ($holiday) {
            return $holiday->date->format('Y-m');
        });

        $calendarData = [];
        foreach ($groupedByMonth as $monthKey => $monthHolidays) {
            $date = Carbon::parse($monthKey.'-01');
            $calendarData[] = [
                'month' => $date->format('F'),
                'month_number' => $date->month,
                'year' => $date->year,
                'holidays' => HolidayResource::collection($monthHolidays),
            ];
        }

        return response()->json([
            'year' => (int) $year,
            'months' => $calendarData,
            'total_holidays' => $holidays->count(),
        ]);
    }

    /**
     * Copy all holidays from current year to target year.
     *
     * Only HR Manager and HR Staff can copy holidays.
     * Adjusts dates by incrementing the year while maintaining month/day.
     * Returns the list of newly created holidays for HR review.
     */
    public function copyToYear(CopyHolidaysRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-holidays');

        $targetYear = (int) $request->validated('target_year');
        $currentYear = now()->year;

        // Get all holidays from current year
        $currentYearHolidays = Holiday::forYear($currentYear)->get();

        $copiedHolidays = [];

        foreach ($currentYearHolidays as $holiday) {
            // Calculate the new date by changing only the year
            $originalDate = $holiday->date;
            $newDate = $originalDate->copy()->setYear($targetYear);

            // Create the new holiday
            $newHoliday = Holiday::create([
                'name' => $holiday->name,
                'date' => $newDate->format('Y-m-d'),
                'holiday_type' => $holiday->holiday_type,
                'description' => $holiday->description,
                'is_national' => $holiday->is_national,
                'year' => $targetYear,
                'work_location_id' => $holiday->work_location_id,
            ]);

            $newHoliday->load('workLocation');
            $copiedHolidays[] = $newHoliday;
        }

        return response()->json([
            'message' => 'Successfully copied '.count($copiedHolidays)." holidays to {$targetYear}.",
            'copied_count' => count($copiedHolidays),
            'target_year' => $targetYear,
            'holidays' => HolidayResource::collection(collect($copiedHolidays)),
        ]);
    }
}
