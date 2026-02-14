<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBackgroundCheckRequest;
use App\Http\Requests\UpdateBackgroundCheckRequest;
use App\Http\Resources\BackgroundCheckDocumentResource;
use App\Http\Resources\BackgroundCheckResource;
use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use App\Models\JobApplication;
use App\Services\Recruitment\AssessmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BackgroundCheckController extends Controller
{
    public function __construct(
        protected AssessmentService $service
    ) {}

    /**
     * List background checks for a job application.
     */
    public function index(JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $checks = $jobApplication->backgroundChecks()
            ->with('documents')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => BackgroundCheckResource::collection($checks)]);
    }

    /**
     * Store a new background check.
     */
    public function store(StoreBackgroundCheckRequest $request, JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $check = $this->service->createBackgroundCheck($jobApplication, $request->validated());

        return response()->json(['data' => new BackgroundCheckResource($check)], 201);
    }

    /**
     * Update a background check.
     */
    public function update(UpdateBackgroundCheckRequest $request, BackgroundCheck $backgroundCheck): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $check = $this->service->updateBackgroundCheck($backgroundCheck, $request->validated());

        return response()->json(['data' => new BackgroundCheckResource($check->load('documents'))]);
    }

    /**
     * Delete a background check.
     */
    public function destroy(BackgroundCheck $backgroundCheck): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $this->service->deleteBackgroundCheck($backgroundCheck);

        return response()->json(null, 204);
    }

    /**
     * Upload a document for a background check.
     */
    public function uploadDocument(Request $request, BackgroundCheck $backgroundCheck): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        $document = $this->service->uploadDocument($backgroundCheck, $request->file('file'));

        return response()->json(['data' => new BackgroundCheckDocumentResource($document)], 201);
    }

    /**
     * Delete a background check document.
     */
    public function deleteDocument(BackgroundCheckDocument $backgroundCheckDocument): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $this->service->deleteDocument($backgroundCheckDocument);

        return response()->json(null, 204);
    }
}
