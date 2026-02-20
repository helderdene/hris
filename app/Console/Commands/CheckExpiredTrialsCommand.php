<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantUserRole;
use App\Models\Tenant;
use App\Notifications\TrialExpiredNotification;
use Illuminate\Console\Command;

class CheckExpiredTrialsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'billing:check-expired-trials';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find tenants with expired trials and notify admin users';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenants = Tenant::where('trial_ends_at', '<', now())
            ->whereNull('trial_expired_notified_at')
            ->whereDoesntHave('subscriptions', fn ($q) => $q->where('paymongo_status', SubscriptionStatus::Active->value))
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('No expired trials to process.');

            return self::SUCCESS;
        }

        $notified = 0;

        foreach ($tenants as $tenant) {
            $adminUsers = $tenant->users()
                ->wherePivot('role', TenantUserRole::Admin->value)
                ->get();

            foreach ($adminUsers as $user) {
                $user->notify(new TrialExpiredNotification($tenant));
                $notified++;
            }

            $tenant->update(['trial_expired_notified_at' => now()]);

            $this->info("Notified admins for: {$tenant->name}");
        }

        $this->info("Sent {$notified} notification(s) for {$tenants->count()} tenant(s).");

        return self::SUCCESS;
    }
}
