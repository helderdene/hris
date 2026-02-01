<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeAssignmentRequest;
use App\Http\Resources\EmployeeAssignmentHistoryResource;
use App\Models\Employee;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class EmployeeAssignmentController extends Controller
{
    public function __construct(protected AssignmentService $assignmentService) {}

    /**
     * Display the assignment history for an employee.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function index(string $tenant, Employee $employee): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-employees');

        $history = $employee->assignmentHistory()
            ->orderBy('created_at', 'desc')
            ->get();

        return EmployeeAssignmentHistoryResource::collection($history);
    }

    /**
     * Store a new assignment for an employee.
     *
     * Note: $tenant parameter is captured from subdomain but not used directly.
     * Tenant context is resolved via middleware and bound to the app container.
     */
    public function store(StoreEmployeeAssignmentRequest $request, string $tenant, Employee $employee): JsonResponse
    {
        Gate::authorize('can-manage-employees');

        $assignment = $this->assignmentService->createAssignment(
            $employee,
            $request->validated(),
            auth()->id()
        );

        return (new EmployeeAssignmentHistoryResource($assignment))
            ->response()
            ->setStatusCode(201);
    }
}
