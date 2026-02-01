<?php

namespace App\Console\Commands;

use App\Enums\ComplianceAssignmentStatus;
use App\Events\ComplianceAssignmentOverdue;
use App\Models\ComplianceAssignment;
use App\Models\Tenant;
use App\Notifications\ComplianceOverdue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessOverdueComplianceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:process-overdue
                            {--tenant= : Specific tenant ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark overdue compliance assignments and send notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to process.');

            return self::SUCCESS;
        }

        $totalOverdue = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            app()->instance('tenant', $tenant);
            $this->switchToTenantDatabase($tenant);

            $overdueCount = $this->processOverdueForTenant();
            $totalOverdue += $overdueCount;

            if ($overdueCount > 0) {
                $this->info("  Marked {$overdueCount} assignment(s) as overdue");
            }

            DB::purge('tenant');
        }

        $this->info("Total overdue assignments processed: {$totalOverdue}");

        return self::SUCCESS;
    }

    /**
     * Process overdue assignments for a single tenant.
     */
    protected function processOverdueForTenant(): int
    {
        $today = now()->startOfDay();
        $overdueCount = 0;

        // Find assignments that are past due but not yet marked as overdue
        $assignments = ComplianceAssignment::query()
            ->whereIn('status', [
                ComplianceAssignmentStatus::Pending,
                ComplianceAssignmentStatus::InProgress,
            ])
            ->whereNotNull('due_date')
            ->where('due_date', '<', $today)
            ->with(['employee.user', 'complianceCourse.course'])
            ->get();

        foreach ($assignments as $assignment) {
            $daysOverdue = $assignment->due_date->diffInDays($today);

            // Update status to overdue
            $assignment->update(['status' => ComplianceAssignmentStatus::Overdue]);

            // Fire event for listeners to handle escalations
            event(new ComplianceAssignmentOverdue($assignment));

            // Notify the employee
            $user = $assignment->employee?->user;
            if ($user) {
                $user->notify(new ComplianceOverdue($assignment, $daysOverdue));

                $this->info("  {$assignment->employee->full_name}: {$assignment->complianceCourse->course->title} ({$daysOverdue} days overdue)");
            }

            $overdueCount++;
        }

        return $overdueCount;
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
