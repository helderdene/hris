<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAssessmentRequest;
use App\Models\ComplianceAssessmentAttempt;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceModule;
use App\Services\ComplianceAssessmentService;
use App\Services\ComplianceProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ComplianceAssessmentApiController extends Controller
{
    public function __construct(
        protected ComplianceAssessmentService $assessmentService,
        protected ComplianceProgressService $progressService
    ) {}

    /**
     * Get assessment questions for a module.
     */
    public function questions(
        ComplianceAssignment $complianceAssignment,
        ComplianceModule $complianceModule
    ): JsonResponse {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        // Ensure this is an assessment module
        if (! $complianceModule->isAssessment()) {
            abort(422, 'This module is not an assessment.');
        }

        $progress = $this->progressService->getOrCreateProgress($complianceAssignment, $complianceModule);

        $questions = $this->assessmentService->getQuestionsForAttempt($progress);

        return response()->json([
            'questions' => $questions,
            'total_questions' => $questions->count(),
            'passing_score' => $complianceModule->getEffectivePassingScore(),
            'max_attempts' => $complianceModule->getEffectiveMaxAttempts(),
            'attempts_made' => $progress->attempts_made,
            'remaining_attempts' => $this->assessmentService->getRemainingAttempts($progress),
            'can_attempt' => $this->assessmentService->canRetake($progress),
        ]);
    }

    /**
     * Start an assessment attempt.
     */
    public function start(
        Request $request,
        ComplianceAssignment $complianceAssignment,
        ComplianceModule $complianceModule
    ): JsonResponse {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        $progress = $this->progressService->getOrCreateProgress($complianceAssignment, $complianceModule);

        $attempt = $this->assessmentService->startAttempt(
            $progress,
            $request->ip(),
            $request->userAgent()
        );

        $questions = $this->assessmentService->getQuestionsForAttempt($progress);

        return response()->json([
            'attempt_id' => $attempt->id,
            'attempt_number' => $attempt->attempt_number,
            'started_at' => $attempt->started_at->toISOString(),
            'questions' => $questions,
            'remaining_attempts' => $this->assessmentService->getRemainingAttempts($progress) - 1,
        ]);
    }

    /**
     * Submit assessment answers.
     */
    public function submit(
        SubmitAssessmentRequest $request,
        ComplianceAssignment $complianceAssignment,
        ComplianceModule $complianceModule,
        ComplianceAssessmentAttempt $attempt
    ): JsonResponse {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        // Ensure attempt belongs to the correct progress
        $progress = $this->progressService->getOrCreateProgress($complianceAssignment, $complianceModule);
        if ($attempt->compliance_progress_id !== $progress->id) {
            abort(404, 'Attempt not found.');
        }

        $validated = $request->validated();

        $attempt = $this->assessmentService->submitAttempt($attempt, $validated['answers']);

        // Get results with explanations
        $results = $this->assessmentService->getResultsWithExplanations($attempt);

        return response()->json([
            'attempt_id' => $attempt->id,
            'score' => $attempt->score,
            'passed' => $attempt->passed,
            'correct_count' => $attempt->correct_count,
            'total_questions' => $attempt->total_questions,
            'passing_score' => $complianceModule->getEffectivePassingScore(),
            'time_taken_minutes' => $attempt->time_taken_minutes,
            'results' => $results,
            'can_retry' => $this->assessmentService->canRetake($progress),
            'remaining_attempts' => $this->assessmentService->getRemainingAttempts($progress),
            'module_completed' => $progress->fresh()->isCompleted(),
        ]);
    }

    /**
     * Get attempt history for an assessment.
     */
    public function attempts(
        ComplianceAssignment $complianceAssignment,
        ComplianceModule $complianceModule
    ): JsonResponse {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        $progress = $this->progressService->getOrCreateProgress($complianceAssignment, $complianceModule);

        $attempts = $this->assessmentService->getAttempts($progress);

        return response()->json([
            'attempts' => $attempts->map(function ($attempt) {
                return [
                    'id' => $attempt->id,
                    'attempt_number' => $attempt->attempt_number,
                    'started_at' => $attempt->started_at->toISOString(),
                    'completed_at' => $attempt->completed_at?->toISOString(),
                    'score' => $attempt->score,
                    'passed' => $attempt->passed,
                    'correct_count' => $attempt->correct_count,
                    'total_questions' => $attempt->total_questions,
                    'time_taken_minutes' => $attempt->time_taken_minutes,
                ];
            }),
            'best_score' => $progress->best_score,
            'total_attempts' => $progress->attempts_made,
        ]);
    }

    /**
     * Get results for a specific attempt.
     */
    public function attemptResults(
        ComplianceAssignment $complianceAssignment,
        ComplianceModule $complianceModule,
        ComplianceAssessmentAttempt $attempt
    ): JsonResponse {
        $employee = auth()->user()->employee;

        // Ensure the assignment belongs to the authenticated employee
        if ($complianceAssignment->employee_id !== $employee->id) {
            abort(403, 'You do not have access to this assignment.');
        }

        // Ensure attempt belongs to the correct progress
        $progress = $this->progressService->getOrCreateProgress($complianceAssignment, $complianceModule);
        if ($attempt->compliance_progress_id !== $progress->id) {
            abort(404, 'Attempt not found.');
        }

        if (! $attempt->isCompleted()) {
            abort(422, 'This attempt has not been completed yet.');
        }

        $results = $this->assessmentService->getResultsWithExplanations($attempt);

        return response()->json([
            'attempt_number' => $attempt->attempt_number,
            'score' => $attempt->score,
            'passed' => $attempt->passed,
            'correct_count' => $attempt->correct_count,
            'total_questions' => $attempt->total_questions,
            'passing_score' => $complianceModule->getEffectivePassingScore(),
            'results' => $results,
        ]);
    }
}
