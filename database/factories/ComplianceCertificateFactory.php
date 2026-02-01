<?php

namespace Database\Factories;

use App\Models\ComplianceAssignment;
use App\Models\ComplianceCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComplianceCertificate>
 */
class ComplianceCertificateFactory extends Factory
{
    protected $model = ComplianceCertificate::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedDate = fake()->dateTimeBetween('-1 year', 'now');

        return [
            'compliance_assignment_id' => ComplianceAssignment::factory()->completed(),
            'certificate_number' => 'CERT-'.fake()->unique()->regexify('[A-Z0-9]{10}'),
            'issued_date' => $issuedDate,
            'valid_until' => fake()->optional()->dateTimeBetween($issuedDate, '+2 years'),
            'final_score' => fake()->numberBetween(70, 100),
            'file_path' => null,
            'file_name' => null,
            'metadata' => [],
            'is_revoked' => false,
            'revocation_reason' => null,
            'revoked_at' => null,
            'revoked_by' => null,
        ];
    }

    /**
     * Indicate that the certificate is valid.
     */
    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_revoked' => false,
            'valid_until' => fake()->dateTimeBetween('+1 month', '+2 years'),
        ]);
    }

    /**
     * Indicate that the certificate is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_revoked' => false,
            'valid_until' => fake()->dateTimeBetween('-1 year', '-1 day'),
        ]);
    }

    /**
     * Indicate that the certificate is expiring soon.
     */
    public function expiringSoon(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_revoked' => false,
            'valid_until' => fake()->dateTimeBetween('+1 day', '+30 days'),
        ]);
    }

    /**
     * Indicate that the certificate is revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_revoked' => true,
            'revocation_reason' => fake()->sentence(),
            'revoked_at' => now(),
        ]);
    }

    /**
     * Indicate that the certificate has a PDF file.
     */
    public function withFile(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => 'certificates/'.fake()->uuid().'.pdf',
            'file_name' => 'certificate.pdf',
        ]);
    }

    /**
     * Indicate that the certificate has no expiration.
     */
    public function noExpiration(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => null,
        ]);
    }
}
