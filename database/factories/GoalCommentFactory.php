<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GoalComment>
 */
class GoalCommentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = GoalComment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'user_id' => User::factory(),
            'comment' => fake()->paragraph(),
            'is_private' => false,
        ];
    }

    /**
     * Indicate that the comment is private.
     */
    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    /**
     * Set a specific comment text.
     */
    public function withComment(string $comment): static
    {
        return $this->state(fn (array $attributes) => [
            'comment' => $comment,
        ]);
    }
}
