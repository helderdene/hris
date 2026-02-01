<?php

namespace App\Console\Commands;

use App\Enums\ComplianceAssignmentStatus;
use App\Models\ComplianceAssignment;
use App\Models\Tenant;
use App\Notifications\ComplianceExpired;
use App\Notifications\ComplianceExpiringReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireComplianceTrainingCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:expire-training
                            {--tenant= : Specific tenant ID to process}
                            {--reminder-days=* : Days before expiry to send reminders (default: 7, 30, 60)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process expired compliance training and send expiry reminders';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $reminderDays = $this->option('reminder-days') ?: [7, 30, 60];
        $reminderDays = array_map('intval', $reminderDays);

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to process.');

            return self::SUCCESS;
        }

        $totalExpired = 0;
        $totalReminders = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            app()->instance('tenant', $tenant);
            $this->switchToTenantDatabase($tenant);

            $result = $this->processExpiryForTenant($reminderDays);
            $totalExpired += $result['expired'];
            $totalReminders += $result['reminders'];

            if ($result['expired'] > 0) {
                $this->info("  Marked {$result['expired']} assignment(s) as expired");
            }
            if ($result['reminders'] > 0) {
                $this->info("  Sent {$result['reminders']} expiry reminder(s)");
            }

            DB::purge('tenant');
        }

        $this->info("Total expired: {$totalExpired}, Total reminders: {$totalReminders}");

        return self::SUCCESS;
    }

    /**
     * Process expiry for a single tenant.
     *
     * @return array{expired: int, reminders: int}
     */
    protected function processExpiryForTenant(array $reminderDays): array
    {
        $today = now()->startOfDay();
        $expiredCount = 0;
        $reminderCount = 0;

        // Process expired assignments
        $expiredAssignments = ComplianceAssignment::query()
            ->where('status', ComplianceAssignmentStatus::Completed)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', $today)
            ->with(['employee.user', 'complianceCourse.course'])
            ->get();

        foreach ($expiredAssignments as $assignment) {
            $assignment->update(['status' => ComplianceAssignmentStatus::Expired]);

            $user = $assignment->employee?->user;
            if ($user) {
                $user->notify(new ComplianceExpired($assignment));
                $this->info("  EXPIRED: {$assignment->employee->full_name} - {$assignment->complianceCourse->course->title}");
            }

            $expiredCount++;
        }

        // Send expiry reminders
        foreach ($reminderDays as $daysBeforeExpiry) {
            $targetDate = now()->addDays($daysBeforeExpiry)->startOfDay();

            $assignments = ComplianceAssignment::query()
                ->where('status', ComplianceAssignmentStatus::Completed)
                ->whereNotNull('valid_until')
                ->whereDate('valid_until', $targetDate)
                ->with(['employee.user', 'complianceCourse.course'])
                ->get();

            foreach ($assignments as $assignment) {
                $user = $assignment->employee?->user;

                if ($user) {
                    $user->notify(new ComplianceExpiringReminder($assignment, $daysBeforeExpiry));
                    $this->info("  Expiry reminder sent to {$assignment->employee->full_name} ({$daysBeforeExpiry} days)");
                    $reminderCount++;
                }
            }
        }

        return ['expired' => $expiredCount, 'reminders' => $reminderCount];
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
