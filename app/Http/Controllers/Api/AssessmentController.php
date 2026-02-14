<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAssessmentRequest;
use App\Http\Requests\UpdateAssessmentRequest;
use App\Http\Resources\AssessmentResource;
use App\Models\Assessment;
use App\Models\JobApplication;
use App\Services\Recruitment\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AssessmentController extends Controller
{
    public function __construct(
        protected AssessmentService $service
    ) {}

    /**
     * List assessments for a job application.
     */
    public function index(JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $assessments = $jobApplication->assessments()
            ->orderBy('assessed_at', 'desc')
            ->get();

        return response()->json(['data' => AssessmentResource::collection($assessments)]);
    }

    /**
     * Store a new assessment.
     */
    public function store(StoreAssessmentRequest $request, JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $assessment = $this->service->createAssessment($jobApplication, $request->validated());

        return response()->json(['data' => new AssessmentResource($assessment)], 201);
    }

    /**
     * Update an assessment.
     */
    public function update(UpdateAssessmentRequest $request, Assessment $assessment): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $assessment = $this->service->updateAssessment($assessment, $request->validated());

        return response()->json(['data' => new AssessmentResource($assessment)]);
    }

    /**
     * Delete an assessment.
     */
    public function destroy(Assessment $assessment): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $this->service->deleteAssessment($assessment);

        return response()->json(null, 204);
    }
}
