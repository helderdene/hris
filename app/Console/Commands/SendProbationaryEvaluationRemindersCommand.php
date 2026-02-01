<?php

namespace App\Console\Commands;

use App\Enums\ProbationaryEvaluationStatus;
use App\Models\ProbationaryEvaluation;
use App\Models\Tenant;
use App\Notifications\ProbationaryEvaluationDueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendProbationaryEvaluationRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'probationary:send-reminders
                            {--days-before=3 : Send reminder when due date is within X days}
                            {--tenant= : Specific tenant ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications for upcoming or overdue probationary evaluations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $daysBefore = (int) $this->option('days-before');
        $tenantId = $this->option('tenant');

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

            // Set tenant context
            app()->instance('tenant', $tenant);

            // Switch to tenant database
            $this->switchToTenantDatabase($tenant);

            $remindersSent = $this->sendRemindersForTenant($daysBefore);
            $totalReminders += $remindersSent;

            if ($remindersSent > 0) {
                $this->info("  Sent {$remindersSent} reminder(s)");
            }

            // Reset to default connection
            DB::purge('tenant');
        }

        $this->info("Total reminders sent: {$totalReminders}");

        return self::SUCCESS;
    }

    /**
     * Send reminders for evaluations in a single tenant.
     */
    protected function sendRemindersForTenant(int $daysBefore): int
    {
        $remindersSent = 0;

        // Get pending and draft evaluations that are due soon or overdue
        $evaluations = ProbationaryEvaluation::query()
            ->whereIn('status', [
                ProbationaryEvaluationStatus::Pending,
                ProbationaryEvaluationStatus::Draft,
                ProbationaryEvaluationStatus::RevisionRequested,
            ])
            ->where(function ($query) use ($daysBefore) {
                // Due within X days
                $query->where('due_date', '<=', now()->addDays($daysBefore))
                    ->where('due_date', '>=', now());
            })
            ->orWhere(function ($query) {
                // Or overdue (haven't sent reminder yet today)
                $query->whereIn('status', [
                    ProbationaryEvaluationStatus::Pending,
                    ProbationaryEvaluationStatus::Draft,
                    ProbationaryEvaluationStatus::RevisionRequested,
                ])
                    ->where('due_date', '<', now());
            })
            ->with(['evaluator.user', 'employee'])
            ->get();

        foreach ($evaluations as $evaluation) {
            $evaluator = $evaluation->evaluator;

            if ($evaluator && $evaluator->user) {
                // Send notification to the evaluator
                $evaluator->user->notify(new ProbationaryEvaluationDueNotification($evaluation));
                $remindersSent++;
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
