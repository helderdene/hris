<?php

namespace Database\Factories;

use App\Models\BackgroundCheck;
use App\Models\BackgroundCheckDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BackgroundCheckDocument>
 */
class BackgroundCheckDocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = BackgroundCheckDocument::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'background_check_id' => BackgroundCheck::factory(),
            'file_path' => 'background-checks/'.fake()->uuid().'.pdf',
            'file_name' => fake()->word().'.pdf',
            'file_size' => fake()->numberBetween(10000, 5000000),
            'mime_type' => 'application/pdf',
        ];
    }
}
