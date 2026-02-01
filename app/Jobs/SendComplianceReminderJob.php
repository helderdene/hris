<?php

namespace App\Jobs;

use App\Models\ComplianceAssignment;
use App\Notifications\ComplianceDueReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to send a due reminder for a specific compliance assignment.
 */
class SendComplianceReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment,
        public int $daysUntilDue
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = $this->assignment->employee?->user;

        if (! $user) {
            return;
        }

        // Verify the assignment is still pending/in progress
        if (! in_array($this->assignment->status->value, ['pending', 'in_progress'])) {
            return;
        }

        $user->notify(new ComplianceDueReminder($this->assignment, $this->daysUntilDue));
    }
}
