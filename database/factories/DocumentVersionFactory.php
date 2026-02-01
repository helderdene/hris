<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DocumentVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeType = fake()->randomElement([
            'application/pdf',
            'application/msword',
            'image/jpeg',
            'image/png',
        ]);

        $extensions = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        $extension = $extensions[$mimeType];
        $storedFilename = Str::uuid().'_document.'.$extension;

        return [
            'document_id' => Document::factory(),
            'version_number' => 1,
            'stored_filename' => $storedFilename,
            'file_path' => 'tenant-slug/documents/'.fake()->randomNumber(3).'/'.$storedFilename,
            'file_size' => fake()->numberBetween(10000, 10000000),
            'mime_type' => $mimeType,
            'version_notes' => fake()->optional()->sentence(),
            'uploaded_by' => User::factory(),
        ];
    }

    /**
     * Set a specific version number.
     */
    public function version(int $number): static
    {
        return $this->state(fn (array $attributes) => [
            'version_number' => $number,
        ]);
    }

    /**
     * Set version notes.
     */
    public function withNotes(string $notes): static
    {
        return $this->state(fn (array $attributes) => [
            'version_notes' => $notes,
        ]);
    }

    /**
     * Set the uploader.
     */
    public function uploadedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_by' => $user->id,
        ]);
    }
}
