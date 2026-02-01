<?php

namespace Database\Factories;

use App\Enums\DocumentRequestStatus;
use App\Enums\DocumentRequestType;
use App\Models\DocumentRequest;
use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentRequest>
 */
class DocumentRequestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = DocumentRequest::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'document_type' => fake()->randomElement(DocumentRequestType::cases()),
            'status' => DocumentRequestStatus::Pending,
            'notes' => fake()->optional(0.5)->sentence(),
            'admin_notes' => null,
            'processed_at' => null,
            'collected_at' => null,
        ];
    }

    /**
     * Create a request in processing status.
     */
    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentRequestStatus::Processing,
            'processed_at' => now(),
        ]);
    }

    /**
     * Create a request in ready status.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentRequestStatus::Ready,
            'processed_at' => now()->subDay(),
        ]);
    }

    /**
     * Create a request in collected status.
     */
    public function collected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DocumentRequestStatus::Collected,
            'processed_at' => now()->subDays(2),
            'collected_at' => now(),
        ]);
    }

    /**
     * Create a request for a specific employee.
     */
    public function forEmployee(Employee|int $employee): static
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $this->state(fn (array $attributes) => [
            'employee_id' => $employeeId,
        ]);
    }

    /**
     * Create a request with a specific document type.
     */
    public function ofType(DocumentRequestType $type): static
    {
        return $this->state(fn (array $attributes) => [
            'document_type' => $type,
        ]);
    }
}
