<?php

namespace Database\Factories;

use App\Models\HelpArticle;
use App\Models\HelpCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HelpArticle>
 */
class HelpArticleFactory extends Factory
{
    protected $model = HelpArticle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'help_category_id' => HelpCategory::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'excerpt' => fake()->paragraph(1),
            'content' => '<p>'.fake()->paragraphs(3, true).'</p>',
            'sort_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'is_featured' => false,
            'view_count' => 0,
        ];
    }

    /**
     * Indicate that the article is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the article is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
