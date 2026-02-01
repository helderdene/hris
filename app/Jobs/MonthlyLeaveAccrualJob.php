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
 * Scheduled job to process monthly leave accrual for all tenants.
 *
 * Runs on the 1st of each month to credit leave balances for employees
 * with monthly accrual leave types.
 */
class MonthlyLeaveAccrualJob implements ShouldQueue
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

        Log::info('MonthlyLeaveAccrualJob starting', [
            'tenant_count' => $tenants->count(),
            'month' => now()->format('F Y'),
        ]);

        $totalProcessed = 0;
        $totalSkipped = 0;

        foreach ($tenants as $tenant) {
            try {
                $databaseManager->switchConnection($tenant);
                app()->instance('tenant', $tenant);

                $result = $leaveBalanceService->processMonthlyAccrualForAllEmployees();

                $totalProcessed += $result['processed'];
                $totalSkipped += $result['skipped'];

                Log::info('MonthlyLeaveAccrualJob processed tenant', [
                    'tenant_id' => $tenant->id,
                    'tenant_name' => $tenant->name,
                    'processed' => $result['processed'],
                    'skipped' => $result['skipped'],
                ]);
            } catch (\Throwable $e) {
                Log::error('MonthlyLeaveAccrualJob failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('MonthlyLeaveAccrualJob completed', [
            'total_processed' => $totalProcessed,
            'total_skipped' => $totalSkipped,
        ]);
    }
}
