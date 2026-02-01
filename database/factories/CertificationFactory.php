<?php

namespace Database\Factories;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationType;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Certification>
 */
class CertificationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Certification::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedDate = fake()->dateTimeBetween('-2 years', '-1 month');
        $expiryDate = (clone $issuedDate)->modify('+3 years');

        return [
            'employee_id' => Employee::factory(),
            'certification_type_id' => CertificationType::factory(),
            'certificate_number' => strtoupper(fake()->bothify('??-####-????')),
            'issuing_body' => fake()->randomElement([
                'Professional Regulation Commission',
                'TESDA',
                'Philippine Red Cross',
                'Bureau of Fire Protection',
                'Land Transportation Office',
            ]),
            'issued_date' => $issuedDate,
            'expiry_date' => $expiryDate,
            'description' => fake()->optional()->sentence(),
            'status' => CertificationStatus::Draft,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'revoked_at' => null,
            'rejection_reason' => null,
            'revocation_reason' => null,
            'metadata' => null,
            'created_by' => null,
            'approved_by' => null,
            'rejected_by' => null,
            'revoked_by' => null,
        ];
    }

    /**
     * Set as draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationStatus::Draft,
        ]);
    }

    /**
     * Set as pending approval status.
     */
    public function pendingApproval(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationStatus::PendingApproval,
            'submitted_at' => now(),
        ]);
    }

    /**
     * Set as active status.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationStatus::Active,
            'submitted_at' => now()->subDays(7),
            'approved_at' => now()->subDays(5),
        ]);
    }

    /**
     * Set as expired status.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationStatus::Expired,
            'expiry_date' => now()->subDays(30),
            'submitted_at' => now()->subYear(),
            'approved_at' => now()->subYear(),
        ]);
    }

    /**
     * Set as revoked status.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CertificationStatus::Revoked,
            'revoked_at' => now(),
            'revocation_reason' => fake()->sentence(),
        ]);
    }

    /**
     * Set expiry date to a specific date.
     */
    public function expiringOn(\DateTimeInterface|string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => $date,
        ]);
    }

    /**
     * Set expiry date to days from now.
     */
    public function expiringInDays(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => now()->addDays($days),
        ]);
    }

    /**
     * Set as a perpetual certification (no expiry).
     */
    public function perpetual(): static
    {
        return $this->state(fn (array $attributes) => [
            'expiry_date' => null,
        ]);
    }

    /**
     * Set specific dates.
     */
    public function withDates(\DateTimeInterface|string $issuedDate, \DateTimeInterface|string|null $expiryDate): static
    {
        return $this->state(fn (array $attributes) => [
            'issued_date' => $issuedDate,
            'expiry_date' => $expiryDate,
        ]);
    }
}
