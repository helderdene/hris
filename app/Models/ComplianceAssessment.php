<?php

namespace App\Models;

use App\Enums\AssessmentQuestionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ComplianceAssessment model for assessment questions.
 *
 * Stores multiple choice, true/false, and multi-select questions for compliance assessments.
 */
class ComplianceAssessment extends TenantModel
{
    /** @use HasFactory<\Database\Factories\ComplianceAssessmentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'compliance_module_id',
        'question',
        'question_type',
        'options',
        'correct_answers',
        'points',
        'explanation',
        'sort_order',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'question_type' => AssessmentQuestionType::class,
            'options' => 'array',
            'correct_answers' => 'array',
            'points' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the compliance module this assessment belongs to.
     */
    public function complianceModule(): BelongsTo
    {
        return $this->belongsTo(ComplianceModule::class);
    }

    /**
     * Scope to get only active questions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Scope to filter by question type.
     */
    public function scopeOfType(Builder $query, AssessmentQuestionType|string $type): Builder
    {
        $value = $type instanceof AssessmentQuestionType ? $type->value : $type;

        return $query->where('question_type', $value);
    }

    /**
     * Check if the given answers are correct.
     *
     * @param  array<int|string>  $answers
     */
    public function checkAnswer(array $answers): bool
    {
        $correctAnswers = $this->correct_answers;

        if ($this->question_type === AssessmentQuestionType::MultiSelect) {
            // For multi-select, all correct answers must be selected and no incorrect ones
            sort($answers);
            sort($correctAnswers);

            return $answers === $correctAnswers;
        }

        // For single answer questions, check if the answer matches
        return count($answers) === 1 && in_array($answers[0], $correctAnswers, true);
    }

    /**
     * Get the score for the given answers.
     *
     * @param  array<int|string>  $answers
     */
    public function getScore(array $answers): int
    {
        return $this->checkAnswer($answers) ? $this->points : 0;
    }

    /**
     * Check if this is a true/false question.
     */
    public function isTrueFalse(): bool
    {
        return $this->question_type === AssessmentQuestionType::TrueFalse;
    }

    /**
     * Check if this is a multi-select question.
     */
    public function isMultiSelect(): bool
    {
        return $this->question_type === AssessmentQuestionType::MultiSelect;
    }

    /**
     * Get the options formatted for display (without revealing correct answers).
     *
     * @return array<array{index: int, text: string}>
     */
    public function getFormattedOptions(): array
    {
        return collect($this->options)->map(function ($option, $index) {
            return [
                'index' => $index,
                'text' => $option,
            ];
        })->values()->all();
    }
}
