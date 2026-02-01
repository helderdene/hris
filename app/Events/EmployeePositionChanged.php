<?php

namespace App\Events;

use App\Models\Employee;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event fired when an employee's position changes.
 */
class EmployeePositionChanged
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Employee $employee,
        public ?int $previousPositionId = null,
        public ?int $newPositionId = null
    ) {}
}
