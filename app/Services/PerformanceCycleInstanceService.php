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
 * and year, handling different cycle types (annual, mid-year) and calculating appropriate
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

        return match ($cycle->cycle_type) {
            PerformanceCycleType::Annual => $this->generateAnnualInstances($cycle, $year),
            PerformanceCycleType::MidYear => $this->generateMidYearInstances($cycle, $year),
            default => collect(),
        };
    }

    /**
     * Generate annual performance instances (1 instance per year).
     *
     * @return Collection<int, PerformanceCycleInstance>
     */
    public function generateAnnualInstances(PerformanceCycle $cycle, int $year): Collection
    {
        $periods = collect();

        // Check if instance already exists
        $existingInstance = PerformanceCycleInstance::query()
            ->forCycle($cycle->id)
            ->forYear($year)
            ->where('instance_number', 1)
            ->first();

        if ($existingInstance) {
            return $periods;
        }

        $instance = PerformanceCycleInstance::create([
            'performance_cycle_id' => $cycle->id,
            'name' => $this->generateInstanceName($cycle, $year, 1),
            'year' => $year,
            'instance_number' => 1,
            'start_date' => Carbon::create($year, 1, 1),
            'end_date' => Carbon::create($year, 12, 31),
            'status' => PerformanceCycleInstanceStatus::Draft,
        ]);

        $periods->push($instance);

        return $periods;
    }

    /**
     * Generate mid-year performance instances (2 instances per year).
     *
     * @return Collection<int, PerformanceCycleInstance>
     */
    public function generateMidYearInstances(PerformanceCycle $cycle, int $year): Collection
    {
        $periods = collect();

        // First half: January 1 - June 30
        $existingFirst = PerformanceCycleInstance::query()
            ->forCycle($cycle->id)
            ->forYear($year)
            ->where('instance_number', 1)
            ->first();

        if (! $existingFirst) {
            $firstHalf = PerformanceCycleInstance::create([
                'performance_cycle_id' => $cycle->id,
                'name' => $this->generateInstanceName($cycle, $year, 1, true),
                'year' => $year,
                'instance_number' => 1,
                'start_date' => Carbon::create($year, 1, 1),
                'end_date' => Carbon::create($year, 6, 30),
                'status' => PerformanceCycleInstanceStatus::Draft,
            ]);

            $periods->push($firstHalf);
        }

        // Second half: July 1 - December 31
        $existingSecond = PerformanceCycleInstance::query()
            ->forCycle($cycle->id)
            ->forYear($year)
            ->where('instance_number', 2)
            ->first();

        if (! $existingSecond) {
            $secondHalf = PerformanceCycleInstance::create([
                'performance_cycle_id' => $cycle->id,
                'name' => $this->generateInstanceName($cycle, $year, 2, true),
                'year' => $year,
                'instance_number' => 2,
                'start_date' => Carbon::create($year, 7, 1),
                'end_date' => Carbon::create($year, 12, 31),
                'status' => PerformanceCycleInstanceStatus::Draft,
            ]);

            $periods->push($secondHalf);
        }

        return $periods;
    }

    /**
     * Generate a human-readable name for an instance.
     */
    protected function generateInstanceName(
        PerformanceCycle $cycle,
        int $year,
        int $instanceNumber,
        bool $isMidYear = false
    ): string {
        $cycleName = $cycle->name;

        if ($isMidYear) {
            $half = $instanceNumber === 1 ? 'First Half' : 'Second Half';

            return "{$cycleName} {$year} - {$half}";
        }

        return "{$cycleName} {$year}";
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
