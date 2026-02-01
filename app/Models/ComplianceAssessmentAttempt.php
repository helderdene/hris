<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ComplianceAssessmentAttempt model for assessment attempt records.
 *
 * Stores individual assessment attempt details including answers and scores.
 */
class ComplianceAssessmentAttempt extends TenantModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'compliance_progress_id',
        'attempt_number',
        'started_at',
        'completed_at',
        'answers',
        'correct_count',
        'total_questions',
        'score',
        'passed',
        'time_taken_minutes',
        'ip_address',
        'user_agent',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'answers' => 'array',
            'correct_count' => 'integer',
            'total_questions' => 'integer',
            'score' => 'decimal:2',
            'passed' => 'boolean',
            'time_taken_minutes' => 'integer',
        ];
    }

    /**
     * Get the progress record this attempt belongs to.
     */
    public function complianceProgress(): BelongsTo
    {
        return $this->belongsTo(ComplianceProgress::class);
    }

    /**
     * Scope to get passed attempts.
     */
    public function scopePassed(Builder $query): Builder
    {
        return $query->where('passed', true);
    }

    /**
     * Scope to get failed attempts.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('passed', false);
    }

    /**
     * Scope to get completed attempts.
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->whereNotNull('completed_at');
    }

    /**
     * Scope to get the best attempt.
     */
    public function scopeBest(Builder $query): Builder
    {
        return $query->orderByDesc('score');
    }

    /**
     * Check if the attempt is completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * Check if the attempt passed.
     */
    public function hasPassed(): bool
    {
        return $this->passed === true;
    }

    /**
     * Get the percentage score.
     */
    public function getPercentageScore(): float
    {
        if ($this->total_questions === 0) {
            return 0.0;
        }

        return round(($this->correct_count / $this->total_questions) * 100, 2);
    }

    /**
     * Complete the attempt with calculated results.
     *
     * @param  array<int, array<int|string>>  $answers  Keyed by question ID
     * @param  array<ComplianceAssessment>  $questions
     */
    public function completeWithResults(array $answers, array $questions): bool
    {
        $correctCount = 0;
        $totalPoints = 0;
        $earnedPoints = 0;

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
        $passingScore = $this->complianceProgress->complianceModule->getEffectivePassingScore();

        $this->answers = $answers;
        $this->correct_count = $correctCount;
        $this->total_questions = count($questions);
        $this->score = round($score, 2);
        $this->passed = $score >= $passingScore;
        $this->completed_at = now();

        if ($this->started_at) {
            $this->time_taken_minutes = (int) $this->started_at->diffInMinutes(now());
        }

        return $this->save();
    }

    /**
     * Get the answer for a specific question.
     *
     * @return array<int|string>|null
     */
    public function getAnswerForQuestion(int $questionId): ?array
    {
        $answers = $this->answers ?? [];

        if (! isset($answers[$questionId])) {
            return null;
        }

        $answer = $answers[$questionId];

        return is_array($answer) ? $answer : [$answer];
    }
}
