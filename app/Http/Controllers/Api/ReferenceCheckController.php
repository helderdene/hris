<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferenceCheckRequest;
use App\Http\Requests\UpdateReferenceCheckRequest;
use App\Http\Resources\ReferenceCheckResource;
use App\Models\JobApplication;
use App\Models\ReferenceCheck;
use App\Services\Recruitment\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReferenceCheckController extends Controller
{
    public function __construct(
        protected AssessmentService $service
    ) {}

    /**
     * List reference checks for a job application.
     */
    public function index(JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $checks = $jobApplication->referenceChecks()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => ReferenceCheckResource::collection($checks)]);
    }

    /**
     * Store a new reference check.
     */
    public function store(StoreReferenceCheckRequest $request, JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $check = $this->service->createReferenceCheck($jobApplication, $request->validated());

        return response()->json(['data' => new ReferenceCheckResource($check)], 201);
    }

    /**
     * Update a reference check.
     */
    public function update(UpdateReferenceCheckRequest $request, ReferenceCheck $referenceCheck): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $check = $this->service->updateReferenceCheck($referenceCheck, $request->validated());

        return response()->json(['data' => new ReferenceCheckResource($check)]);
    }

    /**
     * Delete a reference check.
     */
    public function destroy(ReferenceCheck $referenceCheck): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $this->service->deleteReferenceCheck($referenceCheck);

        return response()->json(null, 204);
    }

    /**
     * Mark a reference check as contacted.
     */
    public function markContacted(ReferenceCheck $referenceCheck): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $check = $this->service->markContacted($referenceCheck);

        return response()->json(['data' => new ReferenceCheckResource($check)]);
    }
}
