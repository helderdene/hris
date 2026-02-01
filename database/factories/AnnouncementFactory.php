<?php

namespace Database\Factories;

use App\Models\Announcement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    protected $model = Announcement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => 1,
            'title' => fake()->sentence(4),
            'body' => fake()->paragraphs(2, true),
            'published_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'expires_at' => null,
            'is_pinned' => false,
            'created_by' => null,
        ];
    }

    public function pinned(): static
    {
        return $this->state(['is_pinned' => true]);
    }

    public function expired(): static
    {
        return $this->state([
            'published_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);
    }

    public function unpublished(): static
    {
        return $this->state(['published_at' => null]);
    }

    public function scheduled(): static
    {
        return $this->state(['published_at' => now()->addDay()]);
    }
}
