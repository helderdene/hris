<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Resources\EmployeeListResource;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EmployeeController extends Controller
{
    /**
     * Display a paginated listing of employees.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-employees');

        $query = Employee::query()
            ->with(['department', 'position']);

        // Search by name, employee number, or position title
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhereHas('position', function ($positionQuery) use ($search) {
                        $positionQuery->where('title', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by department
        if ($departmentId = $request->get('department_id')) {
            $query->where('department_id', $departmentId);
        }

        // Filter by employment status
        if ($status = $request->get('employment_status')) {
            $query->where('employment_status', $status);
        }

        // Filter by employment type
        if ($type = $request->get('employment_type')) {
            $query->where('employment_type', $type);
        }

        $employees = $query
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->get('per_page', 15));

        return EmployeeListResource::collection($employees);
    }

    /**
     * Store a newly created employee.
     */
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        $employee = Employee::create($request->validated());

        $employee->load(['department', 'position', 'workLocation', 'supervisor']);

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified employee.
     */
    public function show(Employee $employee): EmployeeResource
    {
        Gate::authorize('can-manage-employees');

        $employee->load(['department', 'position', 'workLocation', 'supervisor']);

        return new EmployeeResource($employee);
    }

    /**
     * Update the specified employee.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        Gate::authorize('can-manage-employees');

        $employee->update($request->validated());

        $employee->load(['department', 'position', 'workLocation', 'supervisor']);

        return new EmployeeResource($employee);
    }

    /**
     * Remove the specified employee (soft delete).
     */
    public function destroy(Employee $employee): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        $employee->delete();

        return response()->json([
            'message' => 'Employee deleted successfully.',
        ]);
    }
}
