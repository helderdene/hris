<?php

namespace App\Console\Commands;

use App\Enums\ProbationaryMilestone;
use App\Models\Tenant;
use App\Services\ProbationaryEvaluationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CreateProbationaryEvaluationsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'probationary:create-evaluations
                            {--days-ahead=14 : Days before milestone to create evaluation}
                            {--tenant= : Specific tenant ID to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create probationary evaluations for employees approaching their milestone dates';

    /**
     * Execute the console command.
     */
    public function handle(ProbationaryEvaluationService $service): int
    {
        $daysAhead = (int) $this->option('days-ahead');
        $tenantId = $this->option('tenant');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to process.');

            return self::SUCCESS;
        }

        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            // Set tenant context
            app()->instance('tenant', $tenant);

            // Switch to tenant database
            $this->switchToTenantDatabase($tenant);

            foreach (ProbationaryMilestone::cases() as $milestone) {
                $evaluations = $service->createEvaluationsForMilestone($milestone, $daysAhead);
                $count = count($evaluations);

                if ($count > 0) {
                    $this->info("  Created {$count} {$milestone->label()} evaluation(s)");
                    $totalCreated += $count;
                }
            }

            // Reset to default connection
            DB::purge('tenant');
        }

        $this->info("Total evaluations created: {$totalCreated}");

        return self::SUCCESS;
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
