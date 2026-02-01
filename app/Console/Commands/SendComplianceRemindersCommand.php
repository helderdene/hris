<?php

namespace App\Console\Commands;

use App\Enums\ComplianceAssignmentStatus;
use App\Models\ComplianceAssignment;
use App\Models\Tenant;
use App\Notifications\ComplianceDueReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendComplianceRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:send-reminders
                            {--tenant= : Specific tenant ID to process}
                            {--days=* : Specific days before due to send reminders (default: 1, 3, 7)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications for compliance training due dates';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $reminderDays = $this->option('days') ?: [1, 3, 7];
        $reminderDays = array_map('intval', $reminderDays);

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to process.');

            return self::SUCCESS;
        }

        $totalReminders = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            app()->instance('tenant', $tenant);
            $this->switchToTenantDatabase($tenant);

            $remindersSent = $this->sendRemindersForTenant($reminderDays);
            $totalReminders += $remindersSent;

            if ($remindersSent > 0) {
                $this->info("  Sent {$remindersSent} reminder(s)");
            }

            DB::purge('tenant');
        }

        $this->info("Total reminders sent: {$totalReminders}");

        return self::SUCCESS;
    }

    /**
     * Send reminders for a single tenant.
     */
    protected function sendRemindersForTenant(array $reminderDays): int
    {
        $remindersSent = 0;

        foreach ($reminderDays as $daysBeforeDue) {
            $targetDate = now()->addDays($daysBeforeDue)->startOfDay();

            $assignments = ComplianceAssignment::query()
                ->whereIn('status', [
                    ComplianceAssignmentStatus::Pending,
                    ComplianceAssignmentStatus::InProgress,
                ])
                ->whereNotNull('due_date')
                ->whereDate('due_date', $targetDate)
                ->with(['employee.user', 'complianceCourse.course'])
                ->get();

            foreach ($assignments as $assignment) {
                $user = $assignment->employee?->user;

                if ($user) {
                    $user->notify(new ComplianceDueReminder($assignment, $daysBeforeDue));
                    $remindersSent++;

                    $this->info("  Reminder sent to {$assignment->employee->full_name} for {$assignment->complianceCourse->course->title} ({$daysBeforeDue} days)");
                }
            }
        }

        return $remindersSent;
    }

    /**
     * Switch to tenant's database.
     */
    protected function switchToTenantDatabase(Tenant $tenant): void
    {
        config([
            'database.connections.tenant.database' => $tenant->database_name,
        ]);

        DB::reconnect('tenant');
    }
}
