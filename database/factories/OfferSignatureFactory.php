<?php

namespace Database\Factories;

use App\Models\Offer;
use App\Models\OfferSignature;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\OfferSignature>
 */
class OfferSignatureFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = OfferSignature::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'offer_id' => Offer::factory(),
            'signer_type' => fake()->randomElement(['candidate', 'hr_manager', 'hiring_manager']),
            'signer_name' => fake()->name(),
            'signer_email' => fake()->email(),
            'signature_data' => 'data:image/png;base64,'.base64_encode(fake()->text(100)),
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'signed_at' => now(),
        ];
    }

    /**
     * Create a candidate signature.
     */
    public function candidate(): static
    {
        return $this->state(fn () => ['signer_type' => 'candidate']);
    }

    /**
     * Create an HR manager signature.
     */
    public function hrManager(): static
    {
        return $this->state(fn () => ['signer_type' => 'hr_manager']);
    }
}
