<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Http\Resources\DailyTimeRecordResource;
use App\Models\DailyTimeRecord;
use App\Models\Employee;
use App\Services\Dtr\DtrPeriodAggregator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyDtrController extends Controller
{
    public function __construct(
        protected DtrPeriodAggregator $aggregator
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

        return Inertia::render('My/Dtr', [
            'records' => ['data' => $records],
            'summary' => $summary,
            'currentMonth' => $month,
            'hasEmployeeProfile' => $employee !== null,
        ]);
    }
}
