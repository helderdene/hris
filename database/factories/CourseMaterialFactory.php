<?php

namespace Database\Factories;

use App\Enums\CourseMaterialType;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CourseMaterial>
 */
class CourseMaterialFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = CourseMaterial::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $materialType = fake()->randomElement(CourseMaterialType::cases());
        $mimeTypes = $this->getMimeTypesForType($materialType);
        $mimeType = fake()->randomElement($mimeTypes);
        $extension = $this->getExtensionForMimeType($mimeType);

        $title = fake()->sentence(3);
        $fileName = Str::slug(fake()->words(3, true)).'.'.$extension;
        $storedFileName = Str::uuid().'.'.$extension;

        return [
            'course_id' => Course::factory(),
            'title' => $title,
            'description' => fake()->optional()->sentence(),
            'file_name' => $materialType->requiresFile() ? $fileName : null,
            'file_path' => $materialType->requiresFile() ? 'test-tenant/course-materials/1/'.$storedFileName : null,
            'file_size' => $materialType->requiresFile() ? fake()->numberBetween(10000, 50000000) : null,
            'mime_type' => $materialType->requiresFile() ? $mimeType : null,
            'material_type' => $materialType,
            'external_url' => $materialType === CourseMaterialType::Link ? fake()->url() : null,
            'sort_order' => fake()->numberBetween(1, 10),
            'uploaded_by' => null,
        ];
    }

    /**
     * Indicate that the material is a document.
     */
    public function document(): static
    {
        return $this->state(function (array $attributes) {
            $fileName = Str::slug(fake()->words(3, true)).'.pdf';
            $storedFileName = Str::uuid().'.pdf';

            return [
                'material_type' => CourseMaterialType::Document,
                'file_name' => $fileName,
                'file_path' => 'test-tenant/course-materials/1/'.$storedFileName,
                'file_size' => fake()->numberBetween(10000, 10000000),
                'mime_type' => 'application/pdf',
                'external_url' => null,
            ];
        });
    }

    /**
     * Indicate that the material is a video.
     */
    public function video(): static
    {
        return $this->state(function (array $attributes) {
            $fileName = Str::slug(fake()->words(3, true)).'.mp4';
            $storedFileName = Str::uuid().'.mp4';

            return [
                'material_type' => CourseMaterialType::Video,
                'file_name' => $fileName,
                'file_path' => 'test-tenant/course-materials/1/'.$storedFileName,
                'file_size' => fake()->numberBetween(1000000, 50000000),
                'mime_type' => 'video/mp4',
                'external_url' => null,
            ];
        });
    }

    /**
     * Indicate that the material is an image.
     */
    public function image(): static
    {
        return $this->state(function (array $attributes) {
            $fileName = Str::slug(fake()->words(3, true)).'.jpg';
            $storedFileName = Str::uuid().'.jpg';

            return [
                'material_type' => CourseMaterialType::Image,
                'file_name' => $fileName,
                'file_path' => 'test-tenant/course-materials/1/'.$storedFileName,
                'file_size' => fake()->numberBetween(10000, 5000000),
                'mime_type' => 'image/jpeg',
                'external_url' => null,
            ];
        });
    }

    /**
     * Indicate that the material is an external link.
     */
    public function link(): static
    {
        return $this->state(fn (array $attributes) => [
            'material_type' => CourseMaterialType::Link,
            'file_name' => null,
            'file_path' => null,
            'file_size' => null,
            'mime_type' => null,
            'external_url' => fake()->url(),
        ]);
    }

    /**
     * Set a specific uploader for the material.
     */
    public function uploadedBy(?Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'uploaded_by' => $employee?->id,
        ]);
    }

    /**
     * Set a specific course for the material.
     */
    public function forCourse(Course $course): static
    {
        return $this->state(fn (array $attributes) => [
            'course_id' => $course->id,
        ]);
    }

    /**
     * Set a specific sort order for the material.
     */
    public function withSortOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'sort_order' => $order,
        ]);
    }

    /**
     * Get MIME types for a material type.
     *
     * @return array<string>
     */
    private function getMimeTypesForType(CourseMaterialType $type): array
    {
        return match ($type) {
            CourseMaterialType::Document => [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            CourseMaterialType::Video => [
                'video/mp4',
                'video/webm',
            ],
            CourseMaterialType::Image => [
                'image/jpeg',
                'image/png',
            ],
            CourseMaterialType::Link => ['text/html'],
        };
    }

    /**
     * Get file extension for a MIME type.
     */
    private function getExtensionForMimeType(string $mimeType): string
    {
        return match ($mimeType) {
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'video/mp4' => 'mp4',
            'video/webm' => 'webm',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => 'bin',
        };
    }
}
