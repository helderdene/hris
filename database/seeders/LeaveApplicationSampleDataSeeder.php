<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Enums\LeaveApplicationStatus;
use App\Enums\LeaveApprovalDecision;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApplicationApproval;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\select;

class LeaveApplicationSampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(?string $tenantSlug = null): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->error('No tenants found. Please create a tenant first.');

            return;
        }

        if ($tenantSlug === null) {
            $tenantSlug = select(
                label: 'Which tenant do you want to seed leave data for?',
                options: $tenants->pluck('name', 'slug')->toArray(),
                default: 'demo',
            );
        }

        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $this->command->info("Seeding leave data for tenant: {$tenant->name} ({$tenant->slug})");

        // Switch to tenant database
        app(TenantDatabaseManager::class)->switchConnection($tenant);

        // Ensure leave types exist
        $this->call(PhilippineStatutoryLeaveSeeder::class);

        $this->seedLeaveBalances();
        $this->seedLeaveApplications();

        $this->command->info('Leave sample data seeded successfully!');
    }

    protected function seedLeaveBalances(): void
    {
        $this->command->info('Creating leave balances...');

        $employees = Employee::where('employment_status', EmploymentStatus::Active)->get();
        $leaveTypes = LeaveType::all();
        $currentYear = now()->year;

        foreach ($employees as $employee) {
            foreach ($leaveTypes as $leaveType) {
                // Skip gender-restricted leaves for wrong gender
                if ($leaveType->gender_restriction !== null) {
                    if ($leaveType->gender_restriction->value === 'female' && $employee->gender !== 'female') {
                        continue;
                    }
                    if ($leaveType->gender_restriction->value === 'male' && $employee->gender !== 'male') {
                        continue;
                    }
                }

                // Check tenure requirements
                if ($leaveType->min_tenure_months) {
                    $tenureMonths = $employee->getTenureInMonths();
                    if ($tenureMonths < $leaveType->min_tenure_months) {
                        continue;
                    }
                }

                // Create balance for current year
                LeaveBalance::firstOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'year' => $currentYear,
                    ],
                    [
                        'brought_forward' => fake()->boolean(30) ? fake()->randomFloat(2, 0, 3) : 0,
                        'earned' => $leaveType->default_days_per_year ?? 5,
                        'used' => 0,
                        'pending' => 0,
                        'adjustments' => 0,
                        'expired' => 0,
                    ]
                );
            }
        }

        $this->command->info('Created leave balances for '.$employees->count().' employees.');
    }

    protected function seedLeaveApplications(): void
    {
        $this->command->info('Creating leave applications...');

        $employees = Employee::where('employment_status', EmploymentStatus::Active)
            ->whereNotNull('supervisor_id')
            ->with(['supervisor', 'supervisor.supervisor', 'leaveBalances.leaveType'])
            ->get();

        if ($employees->isEmpty()) {
            $this->command->warn('No employees with supervisors found. Skipping leave applications.');

            return;
        }

        $silLeaveType = LeaveType::where('code', 'SIL')->first();
        if (! $silLeaveType) {
            $this->command->warn('No SIL leave type found. Skipping leave applications.');

            return;
        }

        $applicationCount = 0;

        foreach ($employees->take(10) as $employee) {
            $balance = $employee->leaveBalances->where('leave_type_id', $silLeaveType->id)->first();
            if (! $balance) {
                continue;
            }

            // Create various application statuses for realistic data
            $this->createDraftApplication($employee, $silLeaveType);
            $this->createPendingApplication($employee, $silLeaveType, $balance);
            $this->createApprovedApplication($employee, $silLeaveType, $balance);

            // Some employees have rejected or cancelled applications
            if (fake()->boolean(50)) {
                $this->createRejectedApplication($employee, $silLeaveType, $balance);
            }
            if (fake()->boolean(30)) {
                $this->createCancelledApplication($employee, $silLeaveType);
            }

            $applicationCount++;
        }

        $this->command->info("Created leave applications for {$applicationCount} employees.");
    }

    protected function createDraftApplication(Employee $employee, LeaveType $leaveType): void
    {
        $startDate = now()->addDays(fake()->numberBetween(14, 30));
        $endDate = (clone $startDate)->addDays(fake()->numberBetween(0, 2));

        LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => LeaveApplication::calculateTotalDays(
                $startDate->format('Y-m-d'),
                $endDate->format('Y-m-d')
            ),
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'reason' => fake()->randomElement([
                'Personal matters',
                'Family event',
                'Rest and recreation',
                'Medical appointment',
            ]),
            'status' => LeaveApplicationStatus::Draft,
            'current_approval_level' => 0,
            'total_approval_levels' => 1,
        ]);
    }

    protected function createPendingApplication(Employee $employee, LeaveType $leaveType, LeaveBalance $balance): void
    {
        $startDate = now()->addDays(fake()->numberBetween(7, 21));
        $endDate = (clone $startDate)->addDays(fake()->numberBetween(0, 1));
        $totalDays = LeaveApplication::calculateTotalDays(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        // Get supervisor chain
        $supervisors = $this->getSupervisorChain($employee);
        $totalLevels = min(count($supervisors), 2);

        if ($totalLevels === 0) {
            return;
        }

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => $balance->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'reason' => fake()->randomElement([
                'Vacation trip with family',
                'Attending a wedding',
                'Personal errands',
                'Home renovation',
            ]),
            'status' => LeaveApplicationStatus::Pending,
            'current_approval_level' => 1,
            'total_approval_levels' => $totalLevels,
            'submitted_at' => now()->subDays(fake()->numberBetween(1, 3)),
        ]);

        // Create approval records
        foreach ($supervisors as $level => $supervisor) {
            if ($level >= $totalLevels) {
                break;
            }

            LeaveApplicationApproval::create([
                'leave_application_id' => $application->id,
                'approval_level' => $level + 1,
                'approver_type' => $level === 0 ? 'supervisor' : 'department_head',
                'approver_employee_id' => $supervisor->id,
                'approver_name' => $supervisor->full_name,
                'approver_position' => $supervisor->position?->title,
                'decision' => LeaveApprovalDecision::Pending,
            ]);
        }

        // Reserve balance
        $balance->recordPending($totalDays);
    }

    protected function createApprovedApplication(Employee $employee, LeaveType $leaveType, LeaveBalance $balance): void
    {
        $startDate = now()->subDays(fake()->numberBetween(30, 60));
        $endDate = (clone $startDate)->addDays(fake()->numberBetween(0, 2));
        $totalDays = LeaveApplication::calculateTotalDays(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $supervisors = $this->getSupervisorChain($employee);
        $totalLevels = min(count($supervisors), 1);

        if ($totalLevels === 0) {
            return;
        }

        $submittedAt = (clone $startDate)->subDays(fake()->numberBetween(7, 14));
        $approvedAt = (clone $submittedAt)->addDays(fake()->numberBetween(1, 3));

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => $balance->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'reason' => fake()->randomElement([
                'Annual vacation',
                'Family reunion',
                'Rest day',
                'Personal time off',
            ]),
            'status' => LeaveApplicationStatus::Approved,
            'current_approval_level' => $totalLevels,
            'total_approval_levels' => $totalLevels,
            'submitted_at' => $submittedAt,
            'approved_at' => $approvedAt,
        ]);

        // Create approved approval record
        $supervisor = $supervisors[0];
        LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'approval_level' => 1,
            'approver_type' => 'supervisor',
            'approver_employee_id' => $supervisor->id,
            'approver_name' => $supervisor->full_name,
            'approver_position' => $supervisor->position?->title,
            'decision' => LeaveApprovalDecision::Approved,
            'remarks' => fake()->randomElement([
                'Approved. Enjoy your leave!',
                'Approved.',
                'Have a great time!',
                null,
            ]),
            'decided_at' => $approvedAt,
        ]);

        // Record balance usage
        $balance->recordUsage($totalDays);
    }

    protected function createRejectedApplication(Employee $employee, LeaveType $leaveType, LeaveBalance $balance): void
    {
        $startDate = now()->subDays(fake()->numberBetween(14, 45));
        $endDate = (clone $startDate)->addDays(fake()->numberBetween(2, 4));
        $totalDays = LeaveApplication::calculateTotalDays(
            $startDate->format('Y-m-d'),
            $endDate->format('Y-m-d')
        );

        $supervisors = $this->getSupervisorChain($employee);
        if (empty($supervisors)) {
            return;
        }

        $submittedAt = (clone $startDate)->subDays(fake()->numberBetween(7, 10));
        $rejectedAt = (clone $submittedAt)->addDays(fake()->numberBetween(1, 2));

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => null, // Balance was released
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $totalDays,
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'reason' => 'Extended vacation request',
            'status' => LeaveApplicationStatus::Rejected,
            'current_approval_level' => 1,
            'total_approval_levels' => 1,
            'submitted_at' => $submittedAt,
            'rejected_at' => $rejectedAt,
        ]);

        $supervisor = $supervisors[0];
        LeaveApplicationApproval::create([
            'leave_application_id' => $application->id,
            'approval_level' => 1,
            'approver_type' => 'supervisor',
            'approver_employee_id' => $supervisor->id,
            'approver_name' => $supervisor->full_name,
            'approver_position' => $supervisor->position?->title,
            'decision' => LeaveApprovalDecision::Rejected,
            'remarks' => fake()->randomElement([
                'Sorry, we have a critical project deadline during this period.',
                'Insufficient coverage during requested dates.',
                'Please reschedule to a different week.',
            ]),
            'decided_at' => $rejectedAt,
        ]);
    }

    protected function createCancelledApplication(Employee $employee, LeaveType $leaveType): void
    {
        $startDate = now()->addDays(fake()->numberBetween(20, 40));
        $endDate = (clone $startDate)->addDays(1);

        $application = LeaveApplication::create([
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'leave_balance_id' => null,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => 2,
            'is_half_day_start' => false,
            'is_half_day_end' => false,
            'reason' => 'Trip planning',
            'status' => LeaveApplicationStatus::Cancelled,
            'current_approval_level' => 0,
            'total_approval_levels' => 1,
            'submitted_at' => now()->subDays(5),
            'cancelled_at' => now()->subDays(2),
            'cancellation_reason' => fake()->randomElement([
                'Plans changed',
                'Rescheduling to a later date',
                'No longer needed',
            ]),
        ]);
    }

    /**
     * Get supervisor chain for an employee.
     *
     * @return array<int, Employee>
     */
    protected function getSupervisorChain(Employee $employee): array
    {
        $chain = [];
        $current = $employee->supervisor;
        $maxLevels = 3;
        $seen = [];

        while ($current && count($chain) < $maxLevels) {
            if (in_array($current->id, $seen)) {
                break;
            }
            $seen[] = $current->id;
            $chain[] = $current;
            $current = $current->supervisor;
        }

        return $chain;
    }
}
