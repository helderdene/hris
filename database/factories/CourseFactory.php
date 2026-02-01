<?php

namespace Database\Factories;

use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Enums\CourseProviderType;
use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Course>
 */
class CourseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Course::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $courseTitles = [
            'Introduction to Project Management',
            'Advanced Leadership Skills',
            'Effective Communication',
            'Time Management Essentials',
            'Data Analysis Fundamentals',
            'Customer Service Excellence',
            'Workplace Safety Training',
            'Conflict Resolution',
            'Team Building Workshop',
            'Financial Literacy Basics',
        ];

        $deliveryMethod = fake()->randomElement(CourseDeliveryMethod::cases());
        $providerType = fake()->randomElement(CourseProviderType::cases());

        return [
            'title' => fake()->unique()->randomElement($courseTitles).' '.fake()->numberBetween(100, 999),
            'code' => fake()->unique()->regexify('CRS-[A-Z]{2}[0-9]{3}'),
            'description' => fake()->paragraph(),
            'delivery_method' => $deliveryMethod,
            'provider_type' => $providerType,
            'provider_name' => $providerType === CourseProviderType::External ? fake()->company() : null,
            'duration_hours' => fake()->optional()->numberBetween(1, 40),
            'duration_days' => fake()->optional()->numberBetween(1, 5),
            'status' => CourseStatus::Draft,
            'level' => fake()->optional()->randomElement(CourseLevel::cases()),
            'cost' => fake()->optional()->randomFloat(2, 0, 50000),
            'max_participants' => fake()->optional()->numberBetween(5, 50),
            'learning_objectives' => fake()->optional()->randomElements([
                'Understand fundamental concepts',
                'Apply learned techniques in real scenarios',
                'Develop critical thinking skills',
                'Improve team collaboration',
                'Master best practices',
            ], fake()->numberBetween(2, 4)),
            'syllabus' => fake()->optional()->paragraphs(3, true),
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the course is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Draft,
        ]);
    }

    /**
     * Indicate that the course is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Published,
        ]);
    }

    /**
     * Indicate that the course is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CourseStatus::Archived,
        ]);
    }

    /**
     * Indicate that the course is in-person.
     */
    public function inPerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => CourseDeliveryMethod::InPerson,
        ]);
    }

    /**
     * Indicate that the course is virtual.
     */
    public function virtual(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => CourseDeliveryMethod::Virtual,
        ]);
    }

    /**
     * Indicate that the course is e-learning.
     */
    public function eLearning(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => CourseDeliveryMethod::ELearning,
        ]);
    }

    /**
     * Indicate that the course is blended.
     */
    public function blended(): static
    {
        return $this->state(fn (array $attributes) => [
            'delivery_method' => CourseDeliveryMethod::Blended,
        ]);
    }

    /**
     * Indicate that the course is internal.
     */
    public function internal(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_type' => CourseProviderType::Internal,
            'provider_name' => null,
        ]);
    }

    /**
     * Indicate that the course is external.
     */
    public function external(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_type' => CourseProviderType::External,
            'provider_name' => fake()->company(),
        ]);
    }

    /**
     * Indicate the course level.
     */
    public function beginner(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => CourseLevel::Beginner,
        ]);
    }

    /**
     * Indicate the course level.
     */
    public function intermediate(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => CourseLevel::Intermediate,
        ]);
    }

    /**
     * Indicate the course level.
     */
    public function advanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'level' => CourseLevel::Advanced,
        ]);
    }

    /**
     * Indicate the course is free.
     */
    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'cost' => 0,
        ]);
    }

    /**
     * Set a specific creator for the course.
     */
    public function createdBy(?User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user?->id,
        ]);
    }
}
