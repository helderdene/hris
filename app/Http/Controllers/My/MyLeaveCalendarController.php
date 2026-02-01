<?php

namespace App\Http\Controllers\My;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MyLeaveCalendarController extends Controller
{
    /**
     * Display the employee's personal/team leave calendar.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        $leaveTypes = LeaveType::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'category' => $type->leave_category->value,
                'category_label' => $type->leave_category->label(),
            ]);

        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return Inertia::render('My/Leave/Calendar', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'department_id' => $employee->department_id,
            ] : null,
            'departmentName' => $employee?->department?->name,
            'leaveTypes' => $leaveTypes,
            'filters' => [
                'year' => $year,
                'month' => $month,
            ],
        ]);
    }
}
