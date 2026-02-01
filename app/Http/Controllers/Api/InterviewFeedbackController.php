<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreInterviewFeedbackRequest;
use App\Models\Interview;
use App\Services\InterviewService;
use Illuminate\Http\JsonResponse;

class InterviewFeedbackController extends Controller
{
    public function __construct(
        protected InterviewService $interviewService
    ) {}

    /**
     * Get feedback for an interview.
     */
    public function index(string $tenant, Interview $interview): JsonResponse
    {
        $interview->load(['panelists.employee']);

        $feedback = $interview->panelists->map(fn ($panelist) => [
            'id' => $panelist->id,
            'employee' => [
                'id' => $panelist->employee->id,
                'full_name' => $panelist->employee->full_name,
            ],
            'is_lead' => $panelist->is_lead,
            'feedback' => $panelist->feedback,
            'rating' => $panelist->rating,
            'feedback_submitted_at' => $panelist->feedback_submitted_at?->toDateTimeString(),
        ]);

        return response()->json(['data' => $feedback]);
    }

    /**
     * Submit feedback for a panelist.
     */
    public function store(StoreInterviewFeedbackRequest $request, string $tenant, Interview $interview): JsonResponse
    {
        $user = auth()->user();
        $panelist = $interview->panelists()
            ->whereHas('employee', fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (! $panelist) {
            return response()->json(['message' => 'You are not a panelist for this interview.'], 403);
        }

        $panelist = $this->interviewService->submitFeedback(
            $panelist,
            $request->validated('feedback'),
            $request->validated('rating')
        );

        return response()->json(['data' => $panelist]);
    }
}
