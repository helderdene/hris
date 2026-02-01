<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\LeaveBalanceService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Daily job to expire carried-over leave balances past their expiry date.
 *
 * Runs daily to check for balances with expired carry-over and process
 * the forfeiture of those days.
 */
class ExpireCarryOverBalancesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Execute the job.
     */
    public function handle(
        TenantDatabaseManager $databaseManager,
        LeaveBalanceService $leaveBalanceService
    ): void {
        $tenants = Tenant::all();

        Log::info('ExpireCarryOverBalancesJob starting', [
            'tenant_count' => $tenants->count(),
            'date' => now()->toDateString(),
        ]);

        $totalExpired = 0;

        foreach ($tenants as $tenant) {
            try {
                $databaseManager->switchConnection($tenant);
                app()->instance('tenant', $tenant);

                $count = $leaveBalanceService->expireCarriedOverBalances();
                $totalExpired += $count;

                if ($count > 0) {
                    Log::info('ExpireCarryOverBalancesJob processed tenant', [
                        'tenant_id' => $tenant->id,
                        'tenant_name' => $tenant->name,
                        'expired_count' => $count,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('ExpireCarryOverBalancesJob failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('ExpireCarryOverBalancesJob completed', [
            'total_expired' => $totalExpired,
        ]);
    }
}
