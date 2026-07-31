<?php

namespace App\Services;

use App\Enums\PerformanceCycleInstanceStatus;
use App\Enums\PerformanceCycleType;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for generating and managing performance cycle instances.
 *
 * This service provides methods to generate performance evaluation instances for a given cycle
 * and year, handling different cycle types (annual, mid-year, quarterly, monthly) and calculating appropriate
 * date ranges. It also handles participant assignment.
 */
class PerformanceCycleInstanceService
{
    /**
     * Generate performance cycle instances for a cycle and year.
     *
     * @param  bool  $overwriteExisting  Whether to delete existing draft instances first
     * @return Collection<int, PerformanceCycleInstance>
     *
     * @throws \InvalidArgumentException If the cycle type doesn't support generation
     */
    public function generateInstancesForYear(
        PerformanceCycle $cycle,
        int $year,
        bool $overwriteExisting = false
    ): Collection {
        if (! $cycle->isRecurring()) {
            throw new \InvalidArgumentException(
                "Cannot generate recurring instances for {$cycle->cycle_type->label()} cycles."
            );
        }

        if ($overwriteExisting) {
            // Force delete to avoid unique constraint issues with soft-deleted records
            PerformanceCycleInstance::query()
                ->forCycle($cycle->id)
                ->forYear($year)
                ->byStatus(PerformanceCycleInstanceStatus::Draft)
                ->forceDelete();
        }

        $periods = collect();
        $count = $cycle->cycle_type->instancesPerYear();
        $monthsPerPeriod = 12 / $count;

        foreach (range(1, $count) as $number) {
            $exists = PerformanceCycleInstance::query()
                ->forCycle($cycle->id)
                ->forYear($year)
                ->where('instance_number', $number)
                ->exists();

            if ($exists) {
                continue;
            }

            $start = Carbon::create($year, ($number - 1) * $monthsPerPeriod + 1, 1);

            $periods->push(PerformanceCycleInstance::create([
                'performance_cycle_id' => $cycle->id,
                'name' => $this->generateInstanceName($cycle, $year, $number),
                'year' => $year,
                'instance_number' => $number,
                'start_date' => $start,
                'end_date' => $start->copy()->addMonths($monthsPerPeriod)->subDay(),
                'status' => PerformanceCycleInstanceStatus::Draft,
            ]));
        }

        return $periods;
    }

    /**
     * Generate a human-readable name for an instance.
     */
    protected function generateInstanceName(PerformanceCycle $cycle, int $year, int $instanceNumber): string
    {
        $suffix = match ($cycle->cycle_type) {
            PerformanceCycleType::MidYear => $instanceNumber === 1 ? ' - First Half' : ' - Second Half',
            PerformanceCycleType::Quarterly => " - Q{$instanceNumber}",
            PerformanceCycleType::Monthly => ' - '.Carbon::create($year, $instanceNumber, 1)->format('F'),
            default => '',
        };

        return "{$cycle->name} {$year}{$suffix}";
    }

    /**
     * Assign participants to a performance cycle instance.
     *
     * @param  array<int>  $excludedEmployeeIds  Employee IDs to exclude
     * @return Collection<int, PerformanceCycleParticipant>
     */
    public function assignParticipants(
        PerformanceCycleInstance $instance,
        array $excludedEmployeeIds = []
    ): Collection {
        $participants = collect();

        // Get all active employees
        $employees = Employee::query()
            ->active()
            ->get();

        foreach ($employees as $employee) {
            // Check if already assigned
            $existing = PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)
                ->where('employee_id', $employee->id)
                ->first();

            if ($existing) {
                // Update exclusion status if needed
                $isExcluded = in_array($employee->id, $excludedEmployeeIds, true);
                if ($existing->is_excluded !== $isExcluded) {
                    $existing->update(['is_excluded' => $isExcluded]);
                }
                $participants->push($existing);

                continue;
            }

            // Create new participant
            $participant = PerformanceCycleParticipant::create([
                'performance_cycle_instance_id' => $instance->id,
                'employee_id' => $employee->id,
                'manager_id' => $employee->supervisor_id,
                'is_excluded' => in_array($employee->id, $excludedEmployeeIds, true),
                'status' => 'pending',
            ]);

            $participants->push($participant);
        }

        // Update employee count
        $instance->updateEmployeeCount();

        return $participants;
    }

    /**
     * Get summary statistics for instances in a year.
     *
     * @return array{
     *     total_instances: int,
     *     by_status: array<string, int>,
     *     total_participants: int,
     *     completed_participants: int
     * }
     */
    public function getYearSummary(PerformanceCycle $cycle, int $year): array
    {
        $instances = PerformanceCycleInstance::query()
            ->forCycle($cycle->id)
            ->forYear($year)
            ->get();

        $byStatus = [];
        foreach (PerformanceCycleInstanceStatus::cases() as $status) {
            $byStatus[$status->value] = $instances->where('status', $status)->count();
        }

        $totalParticipants = 0;
        $completedParticipants = 0;

        foreach ($instances as $instance) {
            $totalParticipants += $instance->participants()->included()->count();
            $completedParticipants += $instance->participants()->included()->completed()->count();
        }

        return [
            'total_instances' => $instances->count(),
            'by_status' => $byStatus,
            'total_participants' => $totalParticipants,
            'completed_participants' => $completedParticipants,
        ];
    }
}
