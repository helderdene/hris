<?php

namespace App\Services;

use App\Enums\ComplianceProgressStatus;
use App\Models\ComplianceAssessment;
use App\Models\ComplianceAssessmentAttempt;
use App\Models\ComplianceProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing compliance assessments.
 *
 * Handles assessment attempts, grading, and score tracking.
 */
class ComplianceAssessmentService
{
    public function __construct(
        protected ComplianceProgressService $progressService
    ) {}

    /**
     * Start a new assessment attempt.
     *
     * @throws ValidationException
     */
    public function startAttempt(
        ComplianceProgress $progress,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): ComplianceAssessmentAttempt {
        // Validate the module is an assessment
        if (! $progress->complianceModule->isAssessment()) {
            throw ValidationException::withMessages([
                'module' => 'This module is not an assessment.',
            ]);
        }

        // Check if retakes are allowed
        if (! $this->canRetake($progress)) {
            throw ValidationException::withMessages([
                'attempts' => 'Maximum attempts reached for this assessment.',
            ]);
        }

        // Start module progress if not started
        if ($progress->status === ComplianceProgressStatus::NotStarted) {
            $progress->start();
        }

        // Create new attempt
        $attemptNumber = $progress->attempts_made + 1;

        $attempt = ComplianceAssessmentAttempt::create([
            'compliance_progress_id' => $progress->id,
            'attempt_number' => $attemptNumber,
            'started_at' => now(),
            'answers' => [],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Increment attempts made
        $progress->increment('attempts_made');

        return $attempt;
    }

    /**
     * Submit an assessment attempt.
     *
     * @param  array<int, array<int|string>>  $answers  Keyed by question ID
     */
    public function submitAttempt(
        ComplianceAssessmentAttempt $attempt,
        array $answers
    ): ComplianceAssessmentAttempt {
        if ($attempt->isCompleted()) {
            throw ValidationException::withMessages([
                'attempt' => 'This attempt has already been submitted.',
            ]);
        }

        return DB::transaction(function () use ($attempt, $answers) {
            $progress = $attempt->complianceProgress;
            $module = $progress->complianceModule;

            // Get all active questions for this module
            $questions = $module->activeAssessments;

            // Complete the attempt with calculated results
            $attempt->completeWithResults($answers, $questions->all());

            // Update progress with best score
            $this->updateProgressScore($progress, $attempt);

            // Complete the module if passed
            if ($attempt->passed) {
                $this->progressService->completeModule($progress, $attempt->score);
            } elseif (! $this->canRetake($progress)) {
                // Mark as failed if no more attempts
                $progress->fail();
            }

            return $attempt->fresh();
        });
    }

    /**
     * Calculate the score for an attempt.
     *
     * @param  array<int, array<int|string>>  $answers
     * @param  Collection<ComplianceAssessment>  $questions
     * @return array{score: float, correct: int, total: int, passed: bool}
     */
    public function calculateScore(array $answers, Collection $questions, float $passingScore): array
    {
        $totalPoints = 0;
        $earnedPoints = 0;
        $correctCount = 0;

        foreach ($questions as $question) {
            $totalPoints += $question->points;
            $userAnswer = $answers[$question->id] ?? [];

            if (! is_array($userAnswer)) {
                $userAnswer = [$userAnswer];
            }

            if ($question->checkAnswer($userAnswer)) {
                $correctCount++;
                $earnedPoints += $question->points;
            }
        }

        $score = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;

        return [
            'score' => round($score, 2),
            'correct' => $correctCount,
            'total' => $questions->count(),
            'passed' => $score >= $passingScore,
        ];
    }

    /**
     * Check if the employee can retake the assessment.
     */
    public function canRetake(ComplianceProgress $progress): bool
    {
        // Already completed successfully
        if ($progress->status === ComplianceProgressStatus::Completed) {
            $complianceCourse = $progress->complianceAssignment->complianceCourse;

            // Check if retakes after pass are allowed
            if (! $complianceCourse->allow_retakes_after_pass) {
                return false;
            }
        }

        return $progress->canAttempt();
    }

    /**
     * Get the remaining attempts for an assessment.
     */
    public function getRemainingAttempts(ComplianceProgress $progress): int
    {
        return $progress->getRemainingAttempts();
    }

    /**
     * Get all attempts for a progress record.
     *
     * @return Collection<ComplianceAssessmentAttempt>
     */
    public function getAttempts(ComplianceProgress $progress): Collection
    {
        return $progress->assessmentAttempts()->orderBy('attempt_number')->get();
    }

    /**
     * Get the best attempt for a progress record.
     */
    public function getBestAttempt(ComplianceProgress $progress): ?ComplianceAssessmentAttempt
    {
        return $progress->assessmentAttempts()
            ->completed()
            ->best()
            ->first();
    }

    /**
     * Get the questions for an assessment module (without correct answers for security).
     *
     * @return Collection<array{id: int, question: string, type: string, options: array}>
     */
    public function getQuestionsForAttempt(ComplianceProgress $progress): Collection
    {
        $module = $progress->complianceModule;

        return $module->activeAssessments()
            ->ordered()
            ->get()
            ->map(function (ComplianceAssessment $question) {
                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'type' => $question->question_type->value,
                    'options' => $question->getFormattedOptions(),
                    'points' => $question->points,
                ];
            });
    }

    /**
     * Get results with explanations after completing an attempt.
     *
     * @return Collection<array>
     */
    public function getResultsWithExplanations(ComplianceAssessmentAttempt $attempt): Collection
    {
        $module = $attempt->complianceProgress->complianceModule;
        $answers = $attempt->answers;

        return $module->activeAssessments()
            ->ordered()
            ->get()
            ->map(function (ComplianceAssessment $question) use ($answers) {
                $userAnswer = $answers[$question->id] ?? [];
                if (! is_array($userAnswer)) {
                    $userAnswer = [$userAnswer];
                }

                $isCorrect = $question->checkAnswer($userAnswer);

                return [
                    'id' => $question->id,
                    'question' => $question->question,
                    'type' => $question->question_type->value,
                    'options' => $question->options,
                    'user_answer' => $userAnswer,
                    'correct_answers' => $question->correct_answers,
                    'is_correct' => $isCorrect,
                    'points_earned' => $isCorrect ? $question->points : 0,
                    'points_possible' => $question->points,
                    'explanation' => $question->explanation,
                ];
            });
    }

    /**
     * Update progress with the best score from attempts.
     */
    protected function updateProgressScore(
        ComplianceProgress $progress,
        ComplianceAssessmentAttempt $newAttempt
    ): void {
        if ($progress->best_score === null || $newAttempt->score > $progress->best_score) {
            $progress->update(['best_score' => $newAttempt->score]);
        }
    }
}
