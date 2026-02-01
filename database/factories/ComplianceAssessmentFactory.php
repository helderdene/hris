<?php

namespace Database\Factories;

use App\Enums\AssessmentQuestionType;
use App\Models\ComplianceAssessment;
use App\Models\ComplianceModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceAssessment>
 */
class ComplianceAssessmentFactory extends Factory
{
    protected $model = ComplianceAssessment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $questionType = fake()->randomElement(AssessmentQuestionType::cases());

        return [
            'compliance_module_id' => ComplianceModule::factory()->assessment(),
            'question' => fake()->sentence().'?',
            'question_type' => $questionType,
            'options' => $this->generateOptions($questionType),
            'correct_answer' => null, // Will be set based on question type
            'explanation' => fake()->optional()->paragraph(),
            'points' => fake()->randomElement([1, 2, 5, 10]),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Generate options based on question type.
     *
     * @return array<int, string>
     */
    private function generateOptions(AssessmentQuestionType $type): array
    {
        return match ($type) {
            AssessmentQuestionType::TrueFalse => ['True', 'False'],
            AssessmentQuestionType::MultipleChoice => [
                fake()->sentence(3),
                fake()->sentence(3),
                fake()->sentence(3),
                fake()->sentence(3),
            ],
            AssessmentQuestionType::MultiSelect => [
                fake()->sentence(3),
                fake()->sentence(3),
                fake()->sentence(3),
                fake()->sentence(3),
            ],
        };
    }

    /**
     * Indicate that this is a true/false question.
     */
    public function trueFalse(bool $correctAnswer = true): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => AssessmentQuestionType::TrueFalse,
            'options' => ['True', 'False'],
            'correct_answer' => $correctAnswer ? 0 : 1,
        ]);
    }

    /**
     * Indicate that this is a multiple choice question.
     */
    public function multipleChoice(int $correctIndex = 0): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => AssessmentQuestionType::MultipleChoice,
            'options' => [
                fake()->sentence(3),
                fake()->sentence(3),
                fake()->sentence(3),
                fake()->sentence(3),
            ],
            'correct_answer' => $correctIndex,
        ]);
    }

    /**
     * Indicate that this is a multi-select question.
     *
     * @param  array<int>  $correctIndices
     */
    public function multiSelect(array $correctIndices = [0, 2]): static
    {
        return $this->state(fn (array $attributes) => [
            'question_type' => AssessmentQuestionType::MultiSelect,
            'options' => [
                fake()->sentence(3),
                fake()->sentence(3),
                fake()->sentence(3),
                fake()->sentence(3),
            ],
            'correct_answer' => $correctIndices,
        ]);
    }

    /**
     * Set the sort order.
     */
    public function order(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }

    /**
     * Indicate that the question is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
