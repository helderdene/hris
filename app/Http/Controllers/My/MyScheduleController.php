<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyScheduleController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = $user ? Employee::where('user_id', $user->id)->first() : null;

        $scheduleHistory = [];
        $currentSchedule = null;

        if ($employee) {
            $today = now()->toDateString();

            $assignments = $employee->scheduleAssignments()
                ->with('workSchedule')
                ->orderBy('effective_date', 'desc')
                ->get();

            $scheduleHistory = $assignments->map(function ($assignment) use ($today) {
                $effectiveDate = $assignment->effective_date->toDateString();
                $endDate = $assignment->end_date?->toDateString();

                $isCurrent = $effectiveDate <= $today
                    && ($endDate === null || $endDate >= $today);
                $isUpcoming = $effectiveDate > $today;

                return [
                    'id' => $assignment->id,
                    'schedule_name' => $assignment->workSchedule?->name,
                    'schedule_type' => $assignment->workSchedule?->schedule_type?->label(),
                    'shift_name' => $assignment->shift_name,
                    'time_configuration' => $assignment->workSchedule?->time_configuration,
                    'effective_date' => $effectiveDate,
                    'end_date' => $endDate,
                    'is_current' => $isCurrent,
                    'is_upcoming' => $isUpcoming,
                ];
            })->all();

            $currentSchedule = collect($scheduleHistory)->firstWhere('is_current', true);
        }

        return Inertia::render('My/Schedule', [
            'scheduleHistory' => $scheduleHistory,
            'currentSchedule' => $currentSchedule,
            'hasEmployeeProfile' => $employee !== null,
        ]);
    }
}
