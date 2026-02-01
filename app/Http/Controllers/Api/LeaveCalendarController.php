<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeaveApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LeaveCalendarEntryResource;
use App\Models\LeaveApplication;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class LeaveCalendarController extends Controller
{
    /**
     * Get leave applications for the calendar view.
     *
     * Filters by year/month and optionally by department.
     * Returns both approved and pending applications for planning visibility.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        // Calculate month boundaries
        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $query = LeaveApplication::query()
            ->with(['employee.department', 'leaveType'])
            ->whereIn('status', [
                LeaveApplicationStatus::Approved,
                LeaveApplicationStatus::Pending,
            ])
            ->where(function ($q) use ($startOfMonth, $endOfMonth) {
                // Applications that overlap with the requested month
                $q->whereBetween('start_date', [$startOfMonth, $endOfMonth])
                    ->orWhereBetween('end_date', [$startOfMonth, $endOfMonth])
                    ->orWhere(function ($q2) use ($startOfMonth, $endOfMonth) {
                        // Applications that span the entire month
                        $q2->where('start_date', '<=', $startOfMonth)
                            ->where('end_date', '>=', $endOfMonth);
                    });
            });

        // Filter by specific employee if specified
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // Filter by department if specified
        if ($request->filled('department_id')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('department_id', $request->input('department_id'));
            });
        }

        // Filter pending status if show_pending is false
        if (! $request->boolean('show_pending', true)) {
            $query->where('status', LeaveApplicationStatus::Approved);
        }

        $applications = $query->orderBy('start_date')->get();

        return LeaveCalendarEntryResource::collection($applications);
    }
}
