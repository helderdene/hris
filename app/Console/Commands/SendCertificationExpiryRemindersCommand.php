<?php

namespace App\Console\Commands;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\CertificationType;
use App\Models\Tenant;
use App\Notifications\CertificationExpiryReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendCertificationExpiryRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'certifications:send-reminders
                            {--tenant= : Specific tenant ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder notifications for certifications expiring based on configured reminder days';

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

        $totalReminders = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            // Set tenant context
            app()->instance('tenant', $tenant);

            // Switch to tenant database
            $this->switchToTenantDatabase($tenant);

            $remindersSent = $this->sendRemindersForTenant();
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
     * Send reminders for certifications in a single tenant.
     */
    protected function sendRemindersForTenant(): int
    {
        $remindersSent = 0;

        // Get all active certification types with reminder days configured
        $certificationTypes = CertificationType::query()
            ->where('is_active', true)
            ->whereNotNull('reminder_days_before_expiry')
            ->get();

        foreach ($certificationTypes as $certType) {
            $reminderDays = $certType->reminder_days_before_expiry ?? [];

            foreach ($reminderDays as $daysBeforeExpiry) {
                $targetDate = now()->addDays($daysBeforeExpiry)->startOfDay();

                // Get certifications expiring exactly on the target date
                $certifications = Certification::query()
                    ->where('status', CertificationStatus::Active)
                    ->where('certification_type_id', $certType->id)
                    ->whereNotNull('expiry_date')
                    ->whereDate('expiry_date', $targetDate)
                    ->with(['employee.user', 'certificationType'])
                    ->get();

                foreach ($certifications as $certification) {
                    $user = $certification->employee?->user;

                    if ($user) {
                        $user->notify(new CertificationExpiryReminder($certification, $daysBeforeExpiry));
                        $remindersSent++;

                        $this->info("  Reminder sent to {$certification->employee->full_name} for {$certType->name} ({$daysBeforeExpiry} days)");
                    }
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
