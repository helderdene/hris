<?php

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Models\OfferTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Offer>
 */
class OfferFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Offer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'job_application_id' => JobApplication::factory(),
            'offer_template_id' => null,
            'content' => '<p>Offer letter content for candidate.</p>',
            'status' => OfferStatus::Draft,
            'salary' => fake()->randomFloat(2, 20000, 150000),
            'salary_currency' => 'PHP',
            'salary_frequency' => 'monthly',
            'benefits' => ['Health Insurance', '13th Month Pay', 'Paid Leave'],
            'terms' => fake()->optional(0.5)->paragraph(),
            'start_date' => now()->addDays(30)->toDateString(),
            'expiry_date' => now()->addDays(14)->toDateString(),
            'position_title' => fake()->jobTitle(),
            'department' => fake()->optional(0.7)->word(),
            'work_location' => fake()->optional(0.7)->city(),
            'employment_type' => fake()->randomElement(['full_time', 'part_time', 'contract']),
        ];
    }

    /**
     * Set a specific status with corresponding timestamps.
     */
    public function withStatus(OfferStatus $status): static
    {
        $timestamps = [];

        if (in_array($status, [OfferStatus::Sent, OfferStatus::Viewed, OfferStatus::Accepted, OfferStatus::Declined, OfferStatus::Expired, OfferStatus::Revoked])) {
            $timestamps['sent_at'] = now()->subDays(5);
        }

        if (in_array($status, [OfferStatus::Viewed, OfferStatus::Accepted, OfferStatus::Declined])) {
            $timestamps['viewed_at'] = now()->subDays(3);
        }

        if ($status === OfferStatus::Accepted) {
            $timestamps['accepted_at'] = now();
        }

        if ($status === OfferStatus::Declined) {
            $timestamps['declined_at'] = now();
            $timestamps['decline_reason'] = fake()->sentence();
        }

        if ($status === OfferStatus::Expired) {
            $timestamps['expired_at'] = now();
        }

        if ($status === OfferStatus::Revoked) {
            $timestamps['revoked_at'] = now();
            $timestamps['revoke_reason'] = fake()->sentence();
        }

        return $this->state(fn () => array_merge(['status' => $status], $timestamps));
    }

    /**
     * Create an offer with a template.
     */
    public function withTemplate(): static
    {
        return $this->state(fn () => [
            'offer_template_id' => OfferTemplate::factory(),
        ]);
    }
}
