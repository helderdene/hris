<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentResource;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class DepartmentController extends Controller
{
    /**
     * Display a listing of departments.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $departments = Department::query()
            ->with(['parent', 'children'])
            ->orderBy('name')
            ->get();

        return DepartmentResource::collection($departments);
    }

    /**
     * Store a newly created department.
     */
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $department = Department::create($request->validated());

        $department->load(['parent', 'children']);

        return (new DepartmentResource($department))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified department.
     */
    public function show(Department $department): DepartmentResource
    {
        Gate::authorize('can-manage-organization');

        $department->load(['parent', 'children']);

        return new DepartmentResource($department);
    }

    /**
     * Update the specified department.
     */
    public function update(UpdateDepartmentRequest $request, Department $department): DepartmentResource
    {
        Gate::authorize('can-manage-organization');

        $department->update($request->validated());

        $department->load(['parent', 'children']);

        return new DepartmentResource($department);
    }

    /**
     * Remove the specified department (soft delete).
     */
    public function destroy(Department $department): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $department->delete();

        return response()->json([
            'message' => 'Department deleted successfully.',
        ]);
    }
}
