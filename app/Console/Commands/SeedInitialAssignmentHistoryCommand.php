<?php

namespace App\Console\Commands;

use App\Enums\AssignmentType;
use App\Models\Employee;
use App\Models\EmployeeAssignmentHistory;
use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Creates initial assignment history records for employees with existing assignments.
 *
 * This command should be run once during deployment to migrate existing employee
 * assignment data (position_id, department_id, work_location_id, supervisor_id)
 * into the new employee_assignment_history table.
 */
class SeedInitialAssignmentHistoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'employees:seed-assignment-history
                            {--tenant= : Specific tenant slug to process (default: all tenants)}
                            {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed initial assignment history records for employees with existing assignments';

    public function __construct(
        protected TenantDatabaseManager $databaseManager
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $tenantSlug = $this->option('tenant');

        if ($isDryRun) {
            $this->info('[DRY RUN] No changes will be made to the database.');
            $this->newLine();
        }

        // Get tenants to process
        $tenants = $this->getTenants($tenantSlug);

        if ($tenants->isEmpty()) {
            $this->error('No tenants found to process.');

            return self::FAILURE;
        }

        $this->info("Processing {$tenants->count()} tenant(s)...");
        $this->newLine();

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name} ({$tenant->slug})");

            // Switch to tenant database
            $this->databaseManager->switchConnection($tenant);

            [$created, $skipped] = $this->seedTenantAssignmentHistory($tenant, $isDryRun);

            $totalCreated += $created;
            $totalSkipped += $skipped;

            $this->info("  - Created: {$created}, Skipped: {$skipped}");
            $this->newLine();
        }

        $this->newLine();
        $this->info('Summary:');
        $this->info("  Total records created: {$totalCreated}");
        $this->info("  Total employees skipped: {$totalSkipped}");

        if ($isDryRun) {
            $this->newLine();
            $this->warn('This was a dry run. No changes were made. Remove --dry-run to execute.');
        }

        return self::SUCCESS;
    }

    /**
     * Get tenants to process based on the given slug.
     *
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    protected function getTenants(?string $tenantSlug): \Illuminate\Support\Collection
    {
        if ($tenantSlug) {
            $tenant = Tenant::where('slug', $tenantSlug)->first();

            return $tenant ? collect([$tenant]) : collect();
        }

        return Tenant::all();
    }

    /**
     * Seed assignment history records for a specific tenant.
     *
     * @return array{int, int} [created count, skipped count]
     */
    protected function seedTenantAssignmentHistory(Tenant $tenant, bool $isDryRun): array
    {
        $created = 0;
        $skipped = 0;

        // Get all employees with at least one assignment
        $employees = Employee::query()
            ->where(function ($query) {
                $query->whereNotNull('position_id')
                    ->orWhereNotNull('department_id')
                    ->orWhereNotNull('work_location_id')
                    ->orWhereNotNull('supervisor_id');
            })
            ->get();

        if ($employees->isEmpty()) {
            $this->warn('  No employees with assignments found.');

            return [0, 0];
        }

        $this->line("  Found {$employees->count()} employees with assignments.");

        $progressBar = $this->output->createProgressBar($employees->count());
        $progressBar->start();

        foreach ($employees as $employee) {
            $employeeCreated = $this->seedEmployeeAssignmentHistory($employee, $isDryRun);

            if ($employeeCreated > 0) {
                $created += $employeeCreated;
            } else {
                $skipped++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        return [$created, $skipped];
    }

    /**
     * Seed assignment history records for a specific employee.
     *
     * @return int Number of records created
     */
    protected function seedEmployeeAssignmentHistory(Employee $employee, bool $isDryRun): int
    {
        $created = 0;
        $effectiveDate = $employee->hire_date ?? now()->toDateString();

        // Check if employee already has assignment history records
        $existingCount = EmployeeAssignmentHistory::where('employee_id', $employee->id)->count();
        if ($existingCount > 0) {
            return 0; // Skip - already has history
        }

        $assignments = [
            ['type' => AssignmentType::Position, 'value_id' => $employee->position_id],
            ['type' => AssignmentType::Department, 'value_id' => $employee->department_id],
            ['type' => AssignmentType::Location, 'value_id' => $employee->work_location_id],
            ['type' => AssignmentType::Supervisor, 'value_id' => $employee->supervisor_id],
        ];

        foreach ($assignments as $assignment) {
            $type = $assignment['type'];
            $valueId = $assignment['value_id'];
            if ($valueId === null) {
                continue;
            }

            if (! $isDryRun) {
                DB::connection('tenant')->transaction(function () use ($employee, $type, $valueId, $effectiveDate) {
                    EmployeeAssignmentHistory::create([
                        'employee_id' => $employee->id,
                        'assignment_type' => $type,
                        'previous_value_id' => null, // Initial assignment has no previous value
                        'new_value_id' => $valueId,
                        'effective_date' => $effectiveDate,
                        'remarks' => 'Initial assignment (migrated from existing data)',
                        'changed_by' => null, // System migration
                        'ended_at' => null, // Current assignment
                    ]);
                });
            }

            $created++;
        }

        return $created;
    }
}
