<?php

namespace App\Http\Controllers\Leave;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaveCalendarPageController extends Controller
{
    /**
     * Display the leave calendar page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $employee = Employee::where('user_id', $user->id)->first();

        // Get all active departments for filtering
        $departments = Department::query()
            ->active()
            ->orderBy('name')
            ->get()
            ->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
                'code' => $dept->code,
            ]);

        // Get all active leave types for color coding
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

        // Determine the current month/year from request or default to current
        $year = (int) $request->input('year', now()->year);
        $month = (int) $request->input('month', now()->month);

        return Inertia::render('Leave/Calendar/Index', [
            'employee' => $employee ? [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'department_id' => $employee->department_id,
            ] : null,
            'departments' => $departments,
            'leaveTypes' => $leaveTypes,
            'filters' => [
                'year' => $year,
                'month' => $month,
                'department_id' => $request->input('department_id'),
                'show_pending' => $request->boolean('show_pending', true),
            ],
        ]);
    }
}
