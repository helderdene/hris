<?php

namespace App\Console\Commands;

use App\Models\LoanApplicationApproval;
use App\Models\Tenant;
use App\Notifications\LoanApprovalReminder;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sends a daily reminder email to approvers whose pending loan approval is
 * past its deadline. Sends at most one reminder per approval per calendar
 * day (tracked via loan_application_approvals.last_reminder_sent_at).
 */
class SendOverdueLoanRemindersCommand extends Command
{
    protected $signature = 'loan:send-overdue-reminders
        {--tenant= : Slug of a single tenant to process (omit to process all)}';

    protected $description = 'Email overdue loan approval reminders to assigned approvers (daily).';

    public function handle(TenantDatabaseManager $dbManager): int
    {
        $tenantSlug = $this->option('tenant');

        $tenants = $tenantSlug
            ? Tenant::where('slug', $tenantSlug)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');

            return self::SUCCESS;
        }

        $totalSent = 0;

        foreach ($tenants as $tenant) {
            $this->info("→ {$tenant->name} ({$tenant->slug})");

            try {
                $dbManager->switchConnection($tenant);
                app()->instance('tenant', $tenant);

                $sent = $this->processTenant();
                $totalSent += $sent;

                if ($sent > 0) {
                    $this->line("  Sent {$sent} reminder(s)");
                } else {
                    $this->line('  No overdue approvals.');
                }
            } catch (Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
            } finally {
                DB::purge('tenant');
            }
        }

        $this->info("Done. Total reminders sent: {$totalSent}.");

        return self::SUCCESS;
    }

    protected function processTenant(): int
    {
        $cutoffSinceLastReminder = now()->subDay();

        $overdue = LoanApplicationApproval::query()
            ->overdue()
            ->where(function ($query) use ($cutoffSinceLastReminder) {
                $query->whereNull('last_reminder_sent_at')
                    ->orWhere('last_reminder_sent_at', '<', $cutoffSinceLastReminder);
            })
            ->with(['approverEmployee.user', 'loanApplication.employee'])
            ->get();

        $count = 0;
        foreach ($overdue as $approval) {
            $user = $approval->approverEmployee?->user;
            if (! $user) {
                continue;
            }

            $user->notify(new LoanApprovalReminder($approval->loanApplication, $approval));
            $approval->forceFill(['last_reminder_sent_at' => now()])->save();
            $count++;
        }

        return $count;
    }
}
