<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'auditable_type' => Employee::class,
            'auditable_id' => fake()->randomNumber(5),
            'action' => fake()->randomElement(AuditAction::cases()),
            'user_id' => User::factory(),
            'old_values' => null,
            'new_values' => [
                'name' => fake()->name(),
                'email' => fake()->email(),
            ],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }

    /**
     * Indicate that the audit log is for a created action.
     */
    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditAction::Created,
            'old_values' => null,
            'new_values' => [
                'name' => fake()->name(),
                'email' => fake()->email(),
            ],
        ]);
    }

    /**
     * Indicate that the audit log is for an updated action.
     */
    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditAction::Updated,
            'old_values' => [
                'name' => fake()->name(),
            ],
            'new_values' => [
                'name' => fake()->name(),
            ],
        ]);
    }

    /**
     * Indicate that the audit log is for a deleted action.
     */
    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => AuditAction::Deleted,
            'old_values' => [
                'name' => fake()->name(),
                'email' => fake()->email(),
            ],
            'new_values' => null,
        ]);
    }

    /**
     * Set the auditable model.
     */
    public function forModel(string $modelClass, int $modelId): static
    {
        return $this->state(fn (array $attributes) => [
            'auditable_type' => $modelClass,
            'auditable_id' => $modelId,
        ]);
    }

    /**
     * Set the user who made the change.
     */
    public function byUser(User|int $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user instanceof User ? $user->id : $user,
        ]);
    }

    /**
     * Indicate that no user was authenticated.
     */
    public function anonymous(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
        ]);
    }
}
