<?php

namespace Database\Factories;

use App\Enums\ComplianceModuleContentType;
use App\Models\ComplianceCourse;
use App\Models\ComplianceModule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceModule>
 */
class ComplianceModuleFactory extends Factory
{
    protected $model = ComplianceModule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $contentType = fake()->randomElement(ComplianceModuleContentType::cases());

        return [
            'compliance_course_id' => ComplianceCourse::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'content_type' => $contentType,
            'content' => $contentType === ComplianceModuleContentType::Text ? fake()->paragraphs(3, true) : null,
            'file_path' => null,
            'file_name' => null,
            'file_size' => null,
            'mime_type' => null,
            'external_url' => $contentType === ComplianceModuleContentType::Video ? fake()->url() : null,
            'duration_minutes' => fake()->optional()->numberBetween(5, 60),
            'sort_order' => 0,
            'is_required' => true,
            'passing_score' => $contentType === ComplianceModuleContentType::Assessment ? fake()->randomElement([70, 75, 80, 85]) : null,
            'max_attempts' => $contentType === ComplianceModuleContentType::Assessment ? fake()->randomElement([null, 2, 3]) : null,
            'settings' => [],
        ];
    }

    /**
     * Indicate that the module is a video.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => ComplianceModuleContentType::Video,
            'content' => null,
            'external_url' => fake()->url(),
            'duration_minutes' => fake()->numberBetween(10, 45),
        ]);
    }

    /**
     * Indicate that the module is text content.
     */
    public function text(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => ComplianceModuleContentType::Text,
            'content' => fake()->paragraphs(5, true),
            'external_url' => null,
            'duration_minutes' => fake()->numberBetween(5, 15),
        ]);
    }

    /**
     * Indicate that the module is a PDF.
     */
    public function pdf(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => ComplianceModuleContentType::Pdf,
            'content' => null,
            'file_name' => 'document.pdf',
            'mime_type' => 'application/pdf',
            'duration_minutes' => fake()->numberBetween(10, 30),
        ]);
    }

    /**
     * Indicate that the module is an assessment.
     */
    public function assessment(): static
    {
        return $this->state(fn (array $attributes) => [
            'content_type' => ComplianceModuleContentType::Assessment,
            'content' => null,
            'external_url' => null,
            'passing_score' => fake()->randomElement([70, 75, 80, 85]),
            'max_attempts' => fake()->randomElement([null, 2, 3]),
            'duration_minutes' => fake()->numberBetween(15, 45),
        ]);
    }

    /**
     * Indicate that the module is required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => true,
        ]);
    }

    /**
     * Indicate that the module is optional.
     */
    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_required' => false,
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
}
