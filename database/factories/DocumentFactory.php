<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentCategory;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Document>
 */
class DocumentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Document::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $mimeTypes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'image/jpeg',
            'image/png',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        $extensions = [
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
        ];

        $mimeType = fake()->randomElement($mimeTypes);
        $extension = $extensions[$mimeType];
        $originalFilename = fake()->words(3, true).'.'.$extension;
        $storedFilename = Str::uuid().'_'.Str::slug(pathinfo($originalFilename, PATHINFO_FILENAME)).'.'.$extension;

        return [
            'employee_id' => Employee::factory(),
            'document_category_id' => DocumentCategory::factory(),
            'name' => fake()->sentence(3),
            'original_filename' => $originalFilename,
            'stored_filename' => $storedFilename,
            'file_path' => 'tenant-slug/documents/'.fake()->randomNumber(3).'/'.$storedFilename,
            'mime_type' => $mimeType,
            'file_size' => fake()->numberBetween(10000, 10000000),
            'is_company_document' => false,
        ];
    }

    /**
     * Indicate that the document is a company document.
     */
    public function companyDocument(): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => null,
            'is_company_document' => true,
        ]);
    }

    /**
     * Indicate that the document belongs to a specific employee.
     */
    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'employee_id' => $employee->id,
            'is_company_document' => false,
        ]);
    }

    /**
     * Indicate that the document is a PDF.
     */
    public function pdf(): static
    {
        $storedFilename = Str::uuid().'_document.pdf';

        return $this->state(fn (array $attributes) => [
            'original_filename' => 'document.pdf',
            'stored_filename' => $storedFilename,
            'mime_type' => 'application/pdf',
        ]);
    }

    /**
     * Indicate that the document is an image.
     */
    public function image(): static
    {
        $storedFilename = Str::uuid().'_image.jpg';

        return $this->state(fn (array $attributes) => [
            'original_filename' => 'image.jpg',
            'stored_filename' => $storedFilename,
            'mime_type' => 'image/jpeg',
        ]);
    }
}
