<?php

namespace App\Http\Controllers\Api;

use App\Enums\JobPostingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobPostingRequest;
use App\Http\Requests\UpdateJobPostingRequest;
use App\Http\Resources\JobPostingResource;
use App\Models\JobPosting;
use App\Services\JobPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class JobPostingController extends Controller
{
    public function __construct(
        protected JobPostingService $jobPostingService
    ) {}

    /**
     * Display a listing of job postings.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = JobPosting::query()
            ->with(['department', 'position', 'createdByEmployee', 'jobRequisition'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $perPage = $request->input('per_page', 25);

        if ($request->boolean('paginate', true)) {
            return JobPostingResource::collection($query->paginate($perPage));
        }

        return JobPostingResource::collection($query->get());
    }

    /**
     * Store a newly created job posting.
     */
    public function store(StoreJobPostingRequest $request): JsonResponse
    {
        $posting = JobPosting::create($request->validatedWithDefaults());

        $posting->load(['department', 'position', 'createdByEmployee']);

        return (new JobPostingResource($posting))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified job posting.
     */
    public function show(string $tenant, JobPosting $jobPosting): JobPostingResource
    {
        $jobPosting->load(['department', 'position', 'createdByEmployee', 'jobRequisition']);

        return new JobPostingResource($jobPosting);
    }

    /**
     * Update the specified job posting.
     */
    public function update(
        UpdateJobPostingRequest $request,
        string $tenant,
        JobPosting $jobPosting
    ): JobPostingResource {
        if (! $jobPosting->can_be_edited) {
            abort(422, 'This job posting cannot be edited in its current status.');
        }

        $jobPosting->update($request->validated());

        $jobPosting->load(['department', 'position', 'createdByEmployee']);

        return new JobPostingResource($jobPosting);
    }

    /**
     * Publish a job posting.
     */
    public function publish(
        Request $request,
        string $tenant,
        JobPosting $jobPosting
    ): JobPostingResource {
        $posting = $this->jobPostingService->publish($jobPosting);

        return new JobPostingResource($posting);
    }

    /**
     * Close a job posting.
     */
    public function close(
        Request $request,
        string $tenant,
        JobPosting $jobPosting
    ): JobPostingResource {
        $posting = $this->jobPostingService->close($jobPosting);

        return new JobPostingResource($posting);
    }

    /**
     * Archive a job posting.
     */
    public function archive(
        Request $request,
        string $tenant,
        JobPosting $jobPosting
    ): JobPostingResource {
        $posting = $this->jobPostingService->archive($jobPosting);

        return new JobPostingResource($posting);
    }

    /**
     * Remove the specified job posting.
     */
    public function destroy(string $tenant, JobPosting $jobPosting): JsonResponse
    {
        if ($jobPosting->status !== JobPostingStatus::Draft) {
            return response()->json([
                'message' => 'Only draft job postings can be deleted.',
            ], 422);
        }

        $jobPosting->delete();

        return response()->json([
            'message' => 'Job posting deleted successfully.',
        ]);
    }
}
