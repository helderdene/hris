<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyTimeRecordResource;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Services\Dtr\DtrPeriodAggregator;
use App\Services\Kiosk\KioskClockService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyDtrController extends Controller
{
    public function __construct(
        protected DtrPeriodAggregator $aggregator,
        protected KioskClockService $clockService,
    ) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        $month = $request->input('month', now()->format('Y-m'));
        $startDate = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $records = [];
        $summary = null;

        if ($employee) {
            $dtrRecords = DailyTimeRecord::query()
                ->where('employee_id', $employee->id)
                ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
                ->with(['workSchedule', 'punches.attendanceLog'])
                ->orderBy('date', 'desc')
                ->get();

            $records = DailyTimeRecordResource::collection($dtrRecords);
            $summary = $this->aggregator->getSummary($employee, $startDate, $endDate);
        }

        // Self-service clock-in data
        $workLocation = $employee?->workLocation;
        $selfServiceEnabled = $workLocation?->self_service_clockin_enabled ?? false;
        $lastPunch = $employee ? $this->clockService->getLastPunch($employee) : null;

        return Inertia::render('My/Dtr', [
            'records' => ['data' => $records],
            'summary' => $summary,
            'currentMonth' => $month,
            'hasEmployeeProfile' => $employee !== null,
            'selfServiceEnabled' => $selfServiceEnabled,
            'clockStatus' => $lastPunch ? [
                'direction' => $lastPunch->direction,
                'logged_at' => $lastPunch->logged_at?->toISOString(),
                'logged_at_human' => $lastPunch->logged_at?->diffForHumans(),
            ] : null,
            'locationCheck' => $workLocation?->location_check ?? 'none',
        ]);
    }
}
