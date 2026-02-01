<?php

namespace App\Jobs;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Services\Payroll\PayrollComputationService;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to process payroll computation for a payroll period.
 *
 * Can process all employees or a specific subset. Updates period
 * status and totals upon completion.
 */
class ProcessPayrollPeriodJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 1800;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var array<int>
     */
    public array $backoff = [60, 300, 900];

    /**
     * Create a new job instance.
     *
     * @param  int  $periodId  The payroll period ID to process
     * @param  int  $tenantId  The tenant ID for database switching
     * @param  array<int>|null  $employeeIds  Specific employees to compute (null for all)
     * @param  bool  $forceRecompute  Whether to recompute existing entries
     */
    public function __construct(
        public int $periodId,
        public int $tenantId,
        public ?array $employeeIds = null,
        public bool $forceRecompute = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        TenantDatabaseManager $databaseManager,
        PayrollComputationService $computationService
    ): void {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning('ProcessPayrollPeriodJob: Tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        $databaseManager->switchConnection($tenant);
        app()->instance('tenant', $tenant);

        $period = PayrollPeriod::find($this->periodId);

        if ($period === null) {
            Log::warning('ProcessPayrollPeriodJob: Period not found', [
                'period_id' => $this->periodId,
            ]);

            return;
        }

        if ($period->status === PayrollPeriodStatus::Closed) {
            Log::warning('ProcessPayrollPeriodJob: Period is closed, skipping', [
                'period_id' => $this->periodId,
            ]);

            return;
        }

        Log::info('ProcessPayrollPeriodJob: Starting payroll computation', [
            'period_id' => $this->periodId,
            'tenant_id' => $this->tenantId,
            'employee_ids' => $this->employeeIds,
            'force_recompute' => $this->forceRecompute,
        ]);

        if ($period->status === PayrollPeriodStatus::Open) {
            $period->update(['status' => PayrollPeriodStatus::Processing]);
        }

        $entries = $computationService->computeForPeriod(
            $period,
            $this->employeeIds,
            $this->forceRecompute
        );

        if ($period->status === PayrollPeriodStatus::Processing) {
            $period->update(['status' => PayrollPeriodStatus::Open]);
        }

        Log::info('ProcessPayrollPeriodJob: Completed', [
            'period_id' => $this->periodId,
            'tenant_id' => $this->tenantId,
            'entries_processed' => $entries->count(),
            'total_gross' => $period->fresh()->total_gross,
            'total_net' => $period->fresh()->total_net,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessPayrollPeriodJob failed', [
            'period_id' => $this->periodId,
            'tenant_id' => $this->tenantId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        $tenant = Tenant::find($this->tenantId);

        if ($tenant) {
            app(TenantDatabaseManager::class)->switchConnection($tenant);

            $period = PayrollPeriod::find($this->periodId);

            if ($period && $period->status === PayrollPeriodStatus::Processing) {
                $period->update(['status' => PayrollPeriodStatus::Open]);
            }
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<string>
     */
    public function tags(): array
    {
        return [
            'payroll',
            'tenant:'.$this->tenantId,
            'period:'.$this->periodId,
        ];
    }
}
