<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobApplicationRequest;
use App\Http\Requests\UpdateApplicationStatusRequest;
use App\Http\Resources\JobApplicationListResource;
use App\Http\Resources\JobApplicationResource;
use App\Models\JobApplication;
use App\Services\Recruitment\JobApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class JobApplicationController extends Controller
{
    public function __construct(
        protected JobApplicationService $applicationService
    ) {}

    /**
     * Display applications for a job posting.
     */
    public function index(Request $request, int $jobPostingId): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = JobApplication::query()
            ->forPosting($jobPostingId)
            ->with(['candidate', 'jobPosting'])
            ->orderBy('applied_at', 'desc');

        if ($request->filled('status')) {
            $query->withStatus($request->input('status'));
        }

        return JobApplicationListResource::collection($query->paginate(25));
    }

    /**
     * Store a newly created application.
     */
    public function store(StoreJobApplicationRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $application = $this->applicationService->createManualApplication($request->validated());
        $application->load(['candidate', 'jobPosting']);

        return (new JobApplicationResource($application))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified application.
     */
    public function show(JobApplication $jobApplication): JobApplicationResource
    {
        $jobApplication->load(['candidate', 'jobPosting', 'assignedToEmployee', 'statusHistories']);

        return new JobApplicationResource($jobApplication);
    }

    /**
     * Update the application status.
     */
    public function updateStatus(
        UpdateApplicationStatusRequest $request,
        JobApplication $jobApplication
    ): JobApplicationResource {
        $newStatus = ApplicationStatus::from($request->validated('status'));

        $application = $this->applicationService->transitionStatus(
            $jobApplication,
            $newStatus,
            $request->validated('notes'),
            $request->validated('rejection_reason')
        );

        $application->load(['candidate', 'jobPosting', 'statusHistories']);

        return new JobApplicationResource($application);
    }

    /**
     * Remove the specified application.
     */
    public function destroy(JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $jobApplication->delete();

        return response()->json(['message' => 'Application deleted successfully.']);
    }
}
