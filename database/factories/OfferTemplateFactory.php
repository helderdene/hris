<?php

namespace Database\Factories;

use App\Models\OfferTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OfferTemplate>
 */
class OfferTemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = OfferTemplate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true).' Offer Template',
            'content' => '<p>Dear {{candidate_name}},</p><p>We are pleased to offer you the position of {{position}} with a salary of {{salary}}. Your start date will be {{start_date}}.</p><p>Benefits: {{benefits}}</p>',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    /**
     * Mark as default template.
     */
    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    /**
     * Mark as inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
