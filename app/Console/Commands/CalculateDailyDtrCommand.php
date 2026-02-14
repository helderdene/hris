<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\Tenant;
use App\Services\Dtr\DtrCalculationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CalculateDailyDtrCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'dtr:calculate-daily
                            {--tenant= : Specific tenant ID or slug to process}
                            {--date= : Specific date to calculate (YYYY-MM-DD, default: yesterday)}
                            {--range=1 : Number of days back to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate daily time records for all active employees with schedule assignments';

    /**
     * Execute the console command.
     */
    public function handle(DtrCalculationService $dtrCalculationService): int
    {
        $dates = $this->resolveDates();
        $tenants = $this->resolveTenants();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to process.');

            return self::SUCCESS;
        }

        $this->info('Processing DTR for '.count($dates).' date(s) across '.$tenants->count().' tenant(s)');

        $totalProcessed = 0;
        $totalErrors = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            app()->instance('tenant', $tenant);
            $this->switchToTenantDatabase($tenant);

            [$processed, $errors] = $this->processEmployeesForTenant($dtrCalculationService, $dates);

            $totalProcessed += $processed;
            $totalErrors += $errors;

            $this->info("  Tenant complete: {$processed} record(s) processed, {$errors} error(s)");

            DB::purge('tenant');
        }

        $this->info("Done. Total: {$totalProcessed} record(s) processed, {$totalErrors} error(s).");

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Resolve the target dates from command options.
     *
     * @return array<int, Carbon>
     */
    protected function resolveDates(): array
    {
        if ($this->option('date')) {
            return [Carbon::parse($this->option('date'))->startOfDay()];
        }

        $range = max(1, (int) $this->option('range'));
        $dates = [];

        for ($i = $range; $i >= 1; $i--) {
            $dates[] = now()->subDays($i)->startOfDay();
        }

        return $dates;
    }

    /**
     * Resolve tenants to process.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Tenant>
     */
    protected function resolveTenants(): \Illuminate\Database\Eloquent\Collection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            return Tenant::all();
        }

        return Tenant::where('id', $tenant)
            ->orWhere('slug', $tenant)
            ->get();
    }

    /**
     * Process all active employees with schedule assignments for a tenant.
     *
     * @param  array<int, Carbon>  $dates
     * @return array{0: int, 1: int}
     */
    protected function processEmployeesForTenant(DtrCalculationService $service, array $dates): array
    {
        $processed = 0;
        $errors = 0;

        Employee::active()
            ->whereHas('scheduleAssignments', fn ($q) => $q->active())
            ->chunkById(100, function ($employees) use ($service, $dates, &$processed, &$errors) {
                foreach ($employees as $employee) {
                    foreach ($dates as $date) {
                        try {
                            $service->calculateForDate($employee, $date);
                            $processed++;
                        } catch (\Throwable $e) {
                            $errors++;
                            $this->error("  Failed for employee #{$employee->id} on {$date->toDateString()}: {$e->getMessage()}");
                        }
                    }
                }
            });

        return [$processed, $errors];
    }

    /**
     * Switch to tenant's database.
     */
    protected function switchToTenantDatabase(Tenant $tenant): void
    {
        config([
            'database.connections.tenant.database' => $tenant->getDatabaseName(),
        ]);

        DB::reconnect('tenant');
    }
}
