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
 * Scheduled job to process year-end leave balance carry-over and forfeiture.
 *
 * Runs on January 1st to:
 * - Calculate unused balances from the previous year
 * - Apply carry-over rules (max days, expiry)
 * - Record forfeiture when carry-over not allowed
 * - Initialize new year balances
 */
class YearEndLeaveProcessingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * The year to process (the year that is ending).
     */
    public function __construct(
        public int $year
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TenantDatabaseManager $databaseManager,
        LeaveBalanceService $leaveBalanceService
    ): void {
        $tenants = Tenant::all();

        Log::info('YearEndLeaveProcessingJob starting', [
            'tenant_count' => $tenants->count(),
            'year' => $this->year,
        ]);

        $totalCarriedOver = 0;
        $totalForfeited = 0;
        $totalInitialized = 0;

        foreach ($tenants as $tenant) {
            try {
                $databaseManager->switchConnection($tenant);
                app()->instance('tenant', $tenant);

                $result = $leaveBalanceService->processYearEnd($this->year);

                $totalCarriedOver += $result['carried_over'];
                $totalForfeited += $result['forfeited'];
                $totalInitialized += $result['initialized'];

                Log::info('YearEndLeaveProcessingJob processed tenant', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'carried_over' => $result['carried_over'],
                    'forfeited' => $result['forfeited'],
                    'initialized' => $result['initialized'],
                ]);
            } catch (\Throwable $e) {
                Log::error('YearEndLeaveProcessingJob failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('YearEndLeaveProcessingJob completed', [
            'year' => $this->year,
            'total_carried_over' => $totalCarriedOver,
            'total_forfeited' => $totalForfeited,
            'total_initialized' => $totalInitialized,
        ]);
    }
}
