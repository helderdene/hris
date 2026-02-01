<?php

use App\Enums\EmploymentStatus;
use App\Models\Employee;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\Tenant;
use App\Services\PerformanceCycleInstanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    $tenant = Tenant::factory()->create();
    app()->instance('tenant', $tenant);
});

describe('PerformanceCycleInstanceService', function () {
    describe('generateInstancesForYear', function () {
        it('generates one instance for annual cycle with correct dates', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();
            $service = new PerformanceCycleInstanceService;

            $result = $service->generateInstancesForYear($cycle, 2026, false);

            expect($result->count())->toBe(1);

            $instance = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
                ->where('year', 2026)
                ->first();

            expect($instance)->not->toBeNull();
            expect($instance->instance_number)->toBe(1);
            expect($instance->start_date->format('Y-m-d'))->toBe('2026-01-01');
            expect($instance->end_date->format('Y-m-d'))->toBe('2026-12-31');
            expect($instance->name)->toContain($cycle->name);
            expect($instance->name)->toContain('2026');
        });

        it('generates two instances for mid-year cycle with correct dates', function () {
            $cycle = PerformanceCycle::factory()->midYear()->create();
            $service = new PerformanceCycleInstanceService;

            $result = $service->generateInstancesForYear($cycle, 2026, false);

            expect($result->count())->toBe(2);

            // First half
            $firstHalf = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
                ->where('year', 2026)
                ->where('instance_number', 1)
                ->first();

            expect($firstHalf)->not->toBeNull();
            expect($firstHalf->start_date->format('Y-m-d'))->toBe('2026-01-01');
            expect($firstHalf->end_date->format('Y-m-d'))->toBe('2026-06-30');
            expect($firstHalf->name)->toContain('First Half');

            // Second half
            $secondHalf = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
                ->where('year', 2026)
                ->where('instance_number', 2)
                ->first();

            expect($secondHalf)->not->toBeNull();
            expect($secondHalf->start_date->format('Y-m-d'))->toBe('2026-07-01');
            expect($secondHalf->end_date->format('Y-m-d'))->toBe('2026-12-31');
            expect($secondHalf->name)->toContain('Second Half');
        });

        it('throws exception for non-recurring cycle types', function () {
            $cycle = PerformanceCycle::factory()->probationary()->create();
            $service = new PerformanceCycleInstanceService;

            expect(fn () => $service->generateInstancesForYear($cycle, 2026, false))
                ->toThrow(InvalidArgumentException::class);
        });

        it('throws exception for project-based cycle', function () {
            $cycle = PerformanceCycle::factory()->projectBased()->create();
            $service = new PerformanceCycleInstanceService;

            expect(fn () => $service->generateInstancesForYear($cycle, 2026, false))
                ->toThrow(InvalidArgumentException::class);
        });

        it('skips existing instances when overwrite is false', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();

            // Create existing instance
            PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->draft()
                ->create([
                    'year' => 2026,
                    'instance_number' => 1,
                    'name' => 'Existing Instance',
                ]);

            $service = new PerformanceCycleInstanceService;
            $result = $service->generateInstancesForYear($cycle, 2026, false);

            expect($result->count())->toBe(0);

            // Verify existing instance is preserved
            $instance = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
                ->where('year', 2026)
                ->first();

            expect($instance->name)->toBe('Existing Instance');
        });

        it('deletes draft instances when overwrite is true', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();

            // Create existing draft instance
            $existingInstance = PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->draft()
                ->create([
                    'year' => 2026,
                    'instance_number' => 1,
                    'name' => 'Old Draft Instance',
                ]);

            $existingInstanceId = $existingInstance->id;

            $service = new PerformanceCycleInstanceService;
            $result = $service->generateInstancesForYear($cycle, 2026, true);

            expect($result->count())->toBe(1);

            // Verify old instance is force deleted (since service uses forceDelete)
            expect(PerformanceCycleInstance::withTrashed()->find($existingInstanceId))->toBeNull();

            // Verify new instance exists
            $newInstance = PerformanceCycleInstance::where('performance_cycle_id', $cycle->id)
                ->where('year', 2026)
                ->first();

            expect($newInstance)->not->toBeNull();
            expect($newInstance->name)->not->toBe('Old Draft Instance');
        });

        it('preserves non-draft instances even with overwrite flag', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();

            // Create existing active instance (should not be deleted)
            $activeInstance = PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->active()
                ->create([
                    'year' => 2026,
                    'instance_number' => 1,
                    'name' => 'Active Instance',
                ]);

            $service = new PerformanceCycleInstanceService;
            $result = $service->generateInstancesForYear($cycle, 2026, true);

            // Should return empty because existing active instance blocks creation
            expect($result->count())->toBe(0);

            // Verify active instance is preserved
            expect(PerformanceCycleInstance::find($activeInstance->id))->not->toBeNull();
        });
    });

    describe('assignParticipants', function () {
        it('assigns all active employees to the instance', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();
            $instance = PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->draft()
                ->create(['employee_count' => 0]);

            Employee::factory()->count(3)->create(['employment_status' => EmploymentStatus::Active]);
            Employee::factory()->create(['employment_status' => EmploymentStatus::Resigned]);
            Employee::factory()->create(['employment_status' => EmploymentStatus::Terminated]);

            $service = new PerformanceCycleInstanceService;
            $result = $service->assignParticipants($instance, []);

            expect($result->count())->toBe(3);
            expect($result->where('is_excluded', true)->count())->toBe(0);

            // Verify participant records
            expect(PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)->count())->toBe(3);

            // Verify employee count updated
            $instance->refresh();
            expect($instance->employee_count)->toBe(3);
        });

        it('assigns supervisor as manager for each participant', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();
            $instance = PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->draft()
                ->create();

            $supervisor = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
            $employee = Employee::factory()->create([
                'employment_status' => 'active',
                'supervisor_id' => $supervisor->id,
            ]);

            $service = new PerformanceCycleInstanceService;
            $service->assignParticipants($instance, []);

            $participant = PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)
                ->where('employee_id', $employee->id)
                ->first();

            expect($participant->manager_id)->toBe($supervisor->id);
        });

        it('marks specified employees as excluded', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();
            $instance = PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->draft()
                ->create(['employee_count' => 0]);

            $employee1 = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
            $employee2 = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);
            $excludedEmployee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

            $service = new PerformanceCycleInstanceService;
            $result = $service->assignParticipants($instance, [$excludedEmployee->id]);

            expect($result->count())->toBe(3);
            expect($result->where('is_excluded', false)->count())->toBe(2);
            expect($result->where('is_excluded', true)->count())->toBe(1);

            // Verify excluded participant
            $excludedParticipant = PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)
                ->where('employee_id', $excludedEmployee->id)
                ->first();

            expect($excludedParticipant->is_excluded)->toBeTrue();

            // Verify employee count only includes non-excluded
            $instance->refresh();
            expect($instance->employee_count)->toBe(2);
        });

        it('updates existing participants rather than duplicating', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();
            $instance = PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->draft()
                ->create();

            $employee = Employee::factory()->create(['employment_status' => EmploymentStatus::Active]);

            // Create existing participant with this employee
            PerformanceCycleParticipant::factory()
                ->for($instance, 'performanceCycleInstance')
                ->create(['employee_id' => $employee->id, 'is_excluded' => false]);

            // Assign again - should update, not duplicate
            $service = new PerformanceCycleInstanceService;
            $result = $service->assignParticipants($instance, []);

            // Should have exactly 1 participant record for this employee (not duplicated)
            expect(PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)
                ->where('employee_id', $employee->id)
                ->count())->toBe(1);
        });

        it('sets all participants to pending status', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();
            $instance = PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->draft()
                ->create();

            Employee::factory()->count(3)->create(['employment_status' => EmploymentStatus::Active]);

            $service = new PerformanceCycleInstanceService;
            $service->assignParticipants($instance, []);

            $participants = PerformanceCycleParticipant::where('performance_cycle_instance_id', $instance->id)->get();

            foreach ($participants as $participant) {
                expect($participant->status)->toBe('pending');
            }
        });
    });

    describe('getYearSummary', function () {
        it('returns summary statistics for a cycle year', function () {
            $cycle = PerformanceCycle::factory()->midYear()->create();

            // Create instances for 2026
            PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->active()
                ->create([
                    'year' => 2026,
                    'instance_number' => 1,
                    'employee_count' => 10,
                ]);

            PerformanceCycleInstance::factory()
                ->for($cycle, 'performanceCycle')
                ->draft()
                ->create([
                    'year' => 2026,
                    'instance_number' => 2,
                    'employee_count' => 0,
                ]);

            $service = new PerformanceCycleInstanceService;
            $summary = $service->getYearSummary($cycle, 2026);

            expect($summary['total_instances'])->toBe(2);
            expect($summary['by_status']['draft'])->toBe(1);
            expect($summary['by_status']['active'])->toBe(1);
        });

        it('returns zeros when no instances exist', function () {
            $cycle = PerformanceCycle::factory()->annual()->create();

            $service = new PerformanceCycleInstanceService;
            $summary = $service->getYearSummary($cycle, 2026);

            expect($summary['total_instances'])->toBe(0);
            expect($summary['total_participants'])->toBe(0);
        });
    });
});
