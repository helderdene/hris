<?php

namespace App\Events;

use App\Models\ComplianceProgress;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a compliance training module is completed.
 */
class ComplianceModuleCompleted
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public ComplianceProgress $progress
    ) {}
}
