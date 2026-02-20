<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceSource;
use App\Http\Resources\AttendanceLogResource;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceLogController extends Controller
{
    /**
     * Display the attendance log list page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-employees');

        $query = AttendanceLog::query()
            ->with(['employee', 'biometricDevice', 'kiosk']);

        // Apply date filters (default to today)
        $dateFrom = $request->input('date_from', now()->toDateString());
        $dateTo = $request->input('date_to', now()->toDateString());

        if ($dateFrom) {
            $query->whereDate('logged_at', '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate('logged_at', '<=', $dateTo);
        }

        // Apply employee filter
        if ($employeeId = $request->input('employee_id')) {
            $query->where('employee_id', $employeeId);
        }

        // Apply device filter
        if ($deviceId = $request->input('device_id')) {
            $query->where('biometric_device_id', $deviceId);
        }

        // Apply source filter
        if ($source = $request->input('source')) {
            $query->where('source', $source);
        }

        $logs = $query->orderBy('logged_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        // Get dropdown data for filters
        $employees = Employee::query()
            ->select(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'employee_number'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ]);

        $devices = BiometricDevice::query()
            ->select(['id', 'name', 'device_identifier'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $sourceOptions = array_map(
            fn (AttendanceSource $s) => ['value' => $s->value, 'label' => $s->label()],
            AttendanceSource::cases()
        );

        return Inertia::render('Attendance/Index', [
            'logs' => AttendanceLogResource::collection($logs),
            'employees' => $employees,
            'devices' => $devices,
            'sourceOptions' => $sourceOptions,
            'filters' => [
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
                'employee_id' => $request->input('employee_id'),
                'device_id' => $request->input('device_id'),
                'source' => $request->input('source'),
            ],
        ]);
    }
}
