<?php

namespace App\Console\Commands;

use App\Models\ComplianceAssignmentRule;
use App\Models\Tenant;
use App\Services\ComplianceRuleEngine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoReassignComplianceCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance:auto-reassign
                            {--tenant= : Specific tenant ID to process}
                            {--rule= : Specific rule ID to process}
                            {--dry-run : Preview changes without applying}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically reassign compliance training based on active rules';

    /**
     * Execute the console command.
     */
    public function handle(ComplianceRuleEngine $ruleEngine): int
    {
        $tenantId = $this->option('tenant');
        $ruleId = $this->option('rule');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found to process.');

            return self::SUCCESS;
        }

        $totalAssignments = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name}");

            app()->instance('tenant', $tenant);
            $this->switchToTenantDatabase($tenant);

            $assignmentsCreated = $this->processRulesForTenant($ruleEngine, $ruleId, $dryRun);
            $totalAssignments += $assignmentsCreated;

            if ($assignmentsCreated > 0) {
                $this->info("  Created {$assignmentsCreated} new assignment(s)");
            }

            DB::purge('tenant');
        }

        $action = $dryRun ? 'would be created' : 'created';
        $this->info("Total assignments {$action}: {$totalAssignments}");

        return self::SUCCESS;
    }

    /**
     * Process rules for a single tenant.
     */
    protected function processRulesForTenant(ComplianceRuleEngine $ruleEngine, ?string $ruleId, bool $dryRun): int
    {
        $assignmentsCreated = 0;

        $query = ComplianceAssignmentRule::query()
            ->where('is_active', true)
            ->with(['complianceCourse.course']);

        if ($ruleId) {
            $query->where('id', $ruleId);
        }

        $rules = $query->get();

        foreach ($rules as $rule) {
            if (! $rule->isEffective()) {
                continue;
            }

            $this->line("  Processing rule: {$rule->name}");

            // Get employees who match the rule but don't have assignments
            $employees = $ruleEngine->getAffectedEmployees($rule);

            foreach ($employees as $employee) {
                // Check if employee already has an active assignment
                $hasActiveAssignment = $employee->complianceAssignments()
                    ->where('compliance_course_id', $rule->compliance_course_id)
                    ->active()
                    ->exists();

                if ($hasActiveAssignment) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("    [DRY RUN] Would assign {$rule->complianceCourse->course->title} to {$employee->full_name}");
                } else {
                    app(\App\Services\ComplianceAssignmentService::class)->assignByRule($rule, $employee);
                    $this->line("    Assigned {$rule->complianceCourse->course->title} to {$employee->full_name}");
                }

                $assignmentsCreated++;
            }
        }

        return $assignmentsCreated;
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
