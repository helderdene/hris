<?php

namespace App\Jobs;

use App\Models\ComplianceAssignment;
use App\Models\Employee;
use App\Notifications\ComplianceOverdueEscalation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job to escalate overdue compliance training to managers and HR.
 */
class EscalateOverdueComplianceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ComplianceAssignment $assignment,
        public int $daysOverdue,
        public string $escalationLevel = 'manager'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $employee = $this->assignment->employee;

        if (! $employee) {
            return;
        }

        // Determine who to notify based on escalation level
        $recipients = $this->getEscalationRecipients($employee);

        foreach ($recipients as $recipient) {
            $user = $recipient->user;
            if ($user) {
                $user->notify(new ComplianceOverdueEscalation(
                    $this->assignment,
                    $employee,
                    $this->daysOverdue,
                    $this->escalationLevel
                ));
            }
        }
    }

    /**
     * Get the recipients for escalation based on level.
     *
     * @return \Illuminate\Support\Collection<int, Employee>
     */
    protected function getEscalationRecipients(Employee $employee): \Illuminate\Support\Collection
    {
        if ($this->escalationLevel === 'hr') {
            // Get HR managers - employees with specific roles or in HR department
            return Employee::query()
                ->whereHas('department', fn ($q) => $q->where('name', 'like', '%HR%')
                    ->orWhere('name', 'like', '%Human Resource%'))
                ->whereHas('position', fn ($q) => $q->where('title', 'like', '%Manager%')
                    ->orWhere('title', 'like', '%Director%'))
                ->with('user')
                ->get();
        }

        // Default: escalate to direct manager
        $manager = $employee->reportingManager;
        if ($manager) {
            return collect([$manager]);
        }

        return collect();
    }
}
