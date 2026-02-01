<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWorkLocationRequest;
use App\Http\Requests\UpdateWorkLocationRequest;
use App\Http\Resources\WorkLocationResource;
use App\Models\WorkLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class WorkLocationController extends Controller
{
    /**
     * Display a listing of work locations.
     */
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $locations = WorkLocation::query()
            ->orderBy('name')
            ->get();

        return WorkLocationResource::collection($locations);
    }

    /**
     * Store a newly created work location.
     */
    public function store(StoreWorkLocationRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $location = WorkLocation::create($request->validated());

        return (new WorkLocationResource($location))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified work location.
     */
    public function show(WorkLocation $location): WorkLocationResource
    {
        Gate::authorize('can-manage-organization');

        return new WorkLocationResource($location);
    }

    /**
     * Update the specified work location.
     */
    public function update(UpdateWorkLocationRequest $request, WorkLocation $location): WorkLocationResource
    {
        Gate::authorize('can-manage-organization');

        $location->update($request->validated());

        return new WorkLocationResource($location);
    }

    /**
     * Remove the specified work location.
     */
    public function destroy(WorkLocation $location): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $location->delete();

        return response()->json([
            'message' => 'Work location deleted successfully.',
        ]);
    }
}
