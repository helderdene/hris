<?php

namespace App\Events;

use App\Models\ComplianceAssignment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a compliance training assignment becomes overdue.
 */
class ComplianceAssignmentOverdue
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment
    ) {}
}
