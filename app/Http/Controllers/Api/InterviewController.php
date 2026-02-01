<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CancelInterviewRequest;
use App\Http\Requests\StoreInterviewRequest;
use App\Http\Requests\UpdateInterviewRequest;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Notifications\InterviewCancelled;
use App\Notifications\InterviewScheduled;
use App\Services\InterviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class InterviewController extends Controller
{
    public function __construct(
        protected InterviewService $interviewService
    ) {}

    /**
     * List interviews for a job application.
     */
    public function index(string $tenant, JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $interviews = $jobApplication->interviews()
            ->with(['panelists.employee'])
            ->orderBy('scheduled_at', 'desc')
            ->get();

        return response()->json(['data' => $interviews]);
    }

    /**
     * Store a new interview.
     */
    public function store(StoreInterviewRequest $request, string $tenant, JobApplication $jobApplication): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $interview = $this->interviewService->create($jobApplication, $request->validated());

        return response()->json(['data' => $interview], 201);
    }

    /**
     * Update an interview.
     */
    public function update(UpdateInterviewRequest $request, string $tenant, Interview $interview): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $interview = $this->interviewService->update($interview, $request->validated());

        return response()->json(['data' => $interview]);
    }

    /**
     * Cancel an interview.
     */
    public function cancel(CancelInterviewRequest $request, string $tenant, Interview $interview): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $interview = $this->interviewService->cancel($interview, $request->validated('cancellation_reason'));

        $interview->load(['jobApplication.candidate', 'panelists.employee']);
        foreach ($interview->panelists as $panelist) {
            if ($panelist->employee->user) {
                $panelist->employee->user->notify(new InterviewCancelled($interview));
            }
        }

        return response()->json(['data' => $interview]);
    }

    /**
     * Send invitation emails to panelists and candidate.
     */
    public function sendInvitations(string $tenant, Interview $interview): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $interview->load(['jobApplication.candidate', 'panelists.employee']);

        foreach ($interview->panelists as $panelist) {
            if ($panelist->employee->user) {
                $panelist->employee->user->notify(new InterviewScheduled($interview));
            }
        }

        $this->interviewService->markInvitationsSent($interview);

        return response()->json(['message' => 'Invitations sent successfully.']);
    }
}
