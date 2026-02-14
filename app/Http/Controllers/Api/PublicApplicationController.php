<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicApplicationRequest;
use App\Models\JobPosting;
use App\Services\Recruitment\JobApplicationService;
use Illuminate\Http\RedirectResponse;

class PublicApplicationController extends Controller
{
    public function __construct(
        protected JobApplicationService $applicationService
    ) {}

    /**
     * Handle a public career page application.
     */
    public function store(StorePublicApplicationRequest $request, string $slug): RedirectResponse
    {
        $posting = JobPosting::query()
            ->publiclyVisible()
            ->where('slug', $slug)
            ->firstOrFail();

        $this->applicationService->createFromPublicApplication(
            $request->validated(),
            $posting->id,
            $request->file('resume')
        );

        return back()->with('success', 'Your application has been submitted successfully.');
    }
}
