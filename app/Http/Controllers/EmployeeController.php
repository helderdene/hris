<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Events\EmployeeCreated;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeAssignmentHistoryResource;
use App\Http\Resources\EmployeeDeviceSyncResource;
use App\Http\Resources\EmployeeListResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDeviceSync;
use App\Models\Position;
use App\Models\WorkLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /**
     * Display the employee list page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-employees');

        $query = Employee::query()
            ->with(['department', 'position', 'workLocation']);

        // Apply search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhereHas('position', function ($positionQuery) use ($search) {
                        $positionQuery->where('title', 'like', "%{$search}%");
                    });
            });
        }

        // Apply department filter
        if ($departmentId = $request->input('department_id')) {
            $query->where('department_id', $departmentId);
        }

        // Apply employment status filter
        if ($status = $request->input('employment_status')) {
            if (EmploymentStatus::isValid($status)) {
                $query->where('employment_status', $status);
            }
        }

        $employees = $query->orderBy('last_name')
            ->orderBy('first_name')
            ->get();

        $departments = Department::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Employees/Index', [
            'employees' => EmployeeListResource::collection($employees),
            'departments' => $departments,
            'filters' => [
                'search' => $request->input('search'),
                'department_id' => $request->input('department_id'),
                'employment_status' => $request->input('employment_status'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(): Response
    {
        Gate::authorize('can-manage-employees');

        return Inertia::render('Employees/Create', [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->orderBy('title')->get(['id', 'title']),
            'workLocations' => WorkLocation::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()
                ->select(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'employee_number'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'full_name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                ]),
            'employmentTypes' => collect(EmploymentType::cases())->map(fn (EmploymentType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'employmentStatuses' => collect(EmploymentStatus::cases())->map(fn (EmploymentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    /**
     * Store a newly created employee in storage.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        $employee = Employee::create($request->validated());

        EmployeeCreated::dispatch($employee);

        return redirect()
            ->route('employees.show', ['tenant' => tenant()->slug, 'employee' => $employee->id])
            ->with('success', 'Employee created successfully.');
    }

    /**
     * Display the employee profile page.
     *
     * Note: $tenant parameter captures the subdomain route parameter but is unused
     * since tenant context is handled by middleware.
     */
    public function show(string $tenant, Employee|int|string $employee): Response
    {
        Gate::authorize('can-manage-employees');

        if (! $employee instanceof Employee) {
            $employee = Employee::findOrFail($employee);
        }

        $employee->load([
            'department',
            'position',
            'workLocation',
            'supervisor',
        ]);

        // Capture the employee ID for deferred prop closure
        $employeeId = $employee->id;

        // Load dropdown data for assignment change modal
        $props = [
            'employee' => new EmployeeResource($employee),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->orderBy('title')->get(['id', 'title']),
            'workLocations' => WorkLocation::query()->orderBy('name')->get(['id', 'name']),
            'supervisorOptions' => Employee::query()
                ->select(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'employee_number'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $emp) => [
                    'id' => $emp->id,
                    'full_name' => $emp->full_name,
                    'employee_number' => $emp->employee_number,
                ]),
            // Deferred prop for assignment history - lazy loaded when accessed
            'assignmentHistory' => Inertia::defer(function () use ($employeeId) {
                $employeeModel = Employee::find($employeeId);
                if (! $employeeModel) {
                    return [];
                }

                $history = $employeeModel->assignmentHistory()
                    ->orderBy('created_at', 'desc')
                    ->get();

                return EmployeeAssignmentHistoryResource::collection($history);
            }),
            // Sync statuses for biometric devices
            'syncStatuses' => EmployeeDeviceSyncResource::collection(
                EmployeeDeviceSync::where('employee_id', $employeeId)
                    ->with('biometricDevice')
                    ->get()
            ),
        ];

        return Inertia::render('Employees/Show', $props);
    }

    /**
     * Show the form for editing the specified employee.
     *
     * Note: $tenant parameter captures the subdomain route parameter but is unused
     * since tenant context is handled by middleware.
     */
    public function edit(string $tenant, Employee|int|string $employee): Response
    {
        Gate::authorize('can-manage-employees');

        if (! $employee instanceof Employee) {
            $employee = Employee::findOrFail($employee);
        }

        $employee->load([
            'department',
            'position',
            'workLocation',
            'supervisor',
        ]);

        return Inertia::render('Employees/Edit', [
            'employee' => new EmployeeResource($employee),
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'positions' => Position::query()->orderBy('title')->get(['id', 'title']),
            'workLocations' => WorkLocation::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()
                ->select(['id', 'first_name', 'middle_name', 'last_name', 'suffix', 'employee_number'])
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $emp) => [
                    'id' => $emp->id,
                    'full_name' => $emp->full_name,
                    'employee_number' => $emp->employee_number,
                ]),
            'employmentTypes' => collect(EmploymentType::cases())->map(fn (EmploymentType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ]),
            'employmentStatuses' => collect(EmploymentStatus::cases())->map(fn (EmploymentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    /**
     * Update the specified employee in storage.
     *
     * Note: $tenant parameter captures the subdomain route parameter but is unused
     * since tenant context is handled by middleware.
     */
    public function update(UpdateEmployeeRequest $request, string $tenant, Employee|int|string $employee): RedirectResponse
    {
        Gate::authorize('can-manage-employees');

        if (! $employee instanceof Employee) {
            $employee = Employee::findOrFail($employee);
        }

        $employee->update($request->validated());

        return redirect()
            ->route('employees.show', ['tenant' => tenant()->slug, 'employee' => $employee->id])
            ->with('success', 'Employee updated successfully.');
    }
}
