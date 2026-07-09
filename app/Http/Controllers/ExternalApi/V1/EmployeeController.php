<?php

namespace App\Http\Controllers\ExternalApi\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExternalApi\V1\EmployeeListResource;
use App\Http\Resources\ExternalApi\V1\EmployeeResource;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EmployeeController extends Controller
{
    /**
     * List employees with pagination and filters.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Employee::query()
            ->with(['department', 'position', 'workLocation']);

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%");
            });
        }

        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->string('employment_status'));
        }

        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->string('employment_type'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->integer('department_id'));
        }

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', $request->string('updated_since'));
        }

        $employees = $query->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate($request->integer('per_page', 50));

        return EmployeeListResource::collection($employees);
    }

    /**
     * Show a single employee's details.
     */
    public function show(int $employee): EmployeeResource
    {
        $employee = Employee::with(['department', 'position', 'workLocation'])
            ->findOrFail($employee);

        return new EmployeeResource($employee);
    }
}
