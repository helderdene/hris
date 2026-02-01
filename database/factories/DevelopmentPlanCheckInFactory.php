<?php

namespace Database\Factories;

use App\Models\DevelopmentPlan;
use App\Models\DevelopmentPlanCheckIn;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DevelopmentPlanCheckIn>
 */
class DevelopmentPlanCheckInFactory extends Factory
{
    protected $model = DevelopmentPlanCheckIn::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'development_plan_id' => DevelopmentPlan::factory(),
            'check_in_date' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->paragraphs(2, true),
            'created_by' => User::factory(),
        ];
    }
}
