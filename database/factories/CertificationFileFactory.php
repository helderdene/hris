<?php

namespace Database\Factories;

use App\Models\Certification;
use App\Models\CertificationFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CertificationFile>
 */
class CertificationFileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CertificationFile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeType = fake()->randomElement([
            'application/pdf',
            'image/jpeg',
            'image/png',
        ]);

        $extensions = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        $extension = $extensions[$mimeType];
        $originalFilename = 'certificate_'.fake()->word().'.'.$extension;
        $storedFilename = Str::uuid().'.'.$extension;

        return [
            'certification_id' => Certification::factory(),
            'file_path' => 'tenant-slug/certifications/'.now()->format('Y/m').'/'.$storedFilename,
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'mime_type' => $mimeType,
            'file_size' => fake()->numberBetween(50000, 5000000),
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * Set a specific MIME type.
     */
    public function pdf(): static
    {
        $storedFilename = Str::uuid().'.pdf';

        return $this->state(fn (array $attributes) => [
            'mime_type' => 'application/pdf',
            'original_filename' => 'certificate.pdf',
            'stored_filename' => $storedFilename,
            'file_path' => 'tenant-slug/certifications/'.now()->format('Y/m').'/'.$storedFilename,
        ]);
    }

    /**
     * Set the uploader.
     */
    public function uploadedBy(User|int $user): static
    {
        $userId = $user instanceof User ? $user->id : $user;

        return $this->state(fn (array $attributes) => [
            'uploaded_by' => $userId,
        ]);
    }
}
