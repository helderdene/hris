<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Enums\ScheduleType;
use App\Models\AttendanceLog;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\EmployeeScheduleAssignment;
use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Models\WorkSchedule;
use App\Services\Dtr\DtrCalculationService;
use App\Services\Tenant\TenantDatabaseManager;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\select;

class DtrSampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Creates work schedules, assigns them to employees, generates
     * attendance logs, and calculates DTR records.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->error('No tenants found. Please create a tenant first.');

            return;
        }

        $tenantSlug = $this->command->option('no-interaction')
            ? $tenants->first()->slug
            : select(
                label: 'Which tenant do you want to seed DTR data for?',
                options: $tenants->pluck('name', 'slug')->toArray(),
                default: $tenants->first()->slug,
            );

        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $this->command->info("Seeding DTR data for tenant: {$tenant->name} ({$tenant->slug})");

        // Switch to tenant database
        app(TenantDatabaseManager::class)->switchConnection($tenant);

        // Seed work schedules
        $schedules = $this->seedWorkSchedules();

        // Ensure we have a biometric device
        $device = $this->ensureBiometricDevice();

        // Assign schedules to active employees
        $this->assignSchedulesToEmployees($schedules);

        // Generate attendance logs for last 2 weeks
        $this->generateAttendanceLogs($device);

        // Calculate DTR records
        $this->calculateDtrRecords();

        $this->command->info('DTR sample data seeded successfully!');
    }

    /**
     * Create or fetch work schedules.
     *
     * @return array<string, WorkSchedule>
     */
    protected function seedWorkSchedules(): array
    {
        $this->command->info('Creating work schedules...');

        $schedules = [];

        // Regular office hours (8-5)
        $schedules['regular'] = WorkSchedule::firstOrCreate(
            ['code' => 'REG-001'],
            [
                'name' => 'Regular Office Hours (8-5)',
                'schedule_type' => ScheduleType::Fixed,
                'description' => 'Standard 8AM-5PM schedule with 1 hour lunch break',
                'status' => 'active',
                'time_configuration' => [
                    'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                    'half_day_saturday' => false,
                    'start_time' => '08:00',
                    'end_time' => '17:00',
                    'saturday_end_time' => null,
                    'break' => [
                        'start_time' => '12:00',
                        'duration_minutes' => 60,
                    ],
                ],
                'overtime_rules' => [
                    'daily_threshold_hours' => 8,
                    'weekly_threshold_hours' => 40,
                    'regular_multiplier' => 1.25,
                    'rest_day_multiplier' => 1.30,
                    'holiday_multiplier' => 2.0,
                ],
                'night_differential' => [
                    'enabled' => false,
                    'start_time' => '22:00',
                    'end_time' => '06:00',
                    'rate_multiplier' => 1.10,
                ],
            ]
        );

        // Early shift (7-4)
        $schedules['early'] = WorkSchedule::firstOrCreate(
            ['code' => 'EARLY-001'],
            [
                'name' => 'Early Shift (7-4)',
                'schedule_type' => ScheduleType::Fixed,
                'description' => 'Early bird 7AM-4PM schedule',
                'status' => 'active',
                'time_configuration' => [
                    'work_days' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                    'half_day_saturday' => false,
                    'start_time' => '07:00',
                    'end_time' => '16:00',
                    'saturday_end_time' => null,
                    'break' => [
                        'start_time' => '11:00',
                        'duration_minutes' => 60,
                    ],
                ],
                'overtime_rules' => [
                    'daily_threshold_hours' => 8,
                    'weekly_threshold_hours' => 40,
                    'regular_multiplier' => 1.25,
                    'rest_day_multiplier' => 1.30,
                    'holiday_multiplier' => 2.0,
                ],
                'night_differential' => [
                    'enabled' => false,
                    'start_time' => '22:00',
                    'end_time' => '06:00',
                    'rate_multiplier' => 1.10,
                ],
            ]
        );

        // Flexible schedule
        $schedules['flexible'] = WorkSchedule::firstOrCreate(
            ['code' => 'FLEX-001'],
            [
                'name' => 'Flexible Hours',
                'schedule_type' => ScheduleType::Flexible,
                'description' => 'Flexible schedule with core hours 10AM-3PM',
                'status' => 'active',
                'time_configuration' => [
                    'required_hours_per_day' => 8,
                    'required_hours_per_week' => 40,
                    'core_hours' => [
                        'start_time' => '10:00',
                        'end_time' => '15:00',
                    ],
                    'flexible_start_window' => [
                        'earliest' => '06:00',
                        'latest' => '10:00',
                    ],
                    'break' => [
                        'start_time' => null,
                        'duration_minutes' => 60,
                    ],
                ],
                'overtime_rules' => [
                    'daily_threshold_hours' => 8,
                    'weekly_threshold_hours' => 40,
                    'regular_multiplier' => 1.25,
                    'rest_day_multiplier' => 1.30,
                    'holiday_multiplier' => 2.0,
                ],
                'night_differential' => [
                    'enabled' => false,
                    'start_time' => '22:00',
                    'end_time' => '06:00',
                    'rate_multiplier' => 1.10,
                ],
            ]
        );

        $this->command->info('  Created '.count($schedules).' work schedules.');

        return $schedules;
    }

    /**
     * Ensure a biometric device exists for attendance logging.
     */
    protected function ensureBiometricDevice(): BiometricDevice
    {
        $device = BiometricDevice::first();

        if ($device !== null) {
            return $device;
        }

        $this->command->info('Creating biometric device...');

        $workLocation = WorkLocation::first();
        if ($workLocation === null) {
            $workLocation = WorkLocation::create([
                'name' => 'Main Office',
                'code' => 'HQ-001',
                'address' => '123 Business Park',
                'city' => 'Makati City',
                'region' => 'Metro Manila',
                'country' => 'PH',
                'postal_code' => '1226',
                'timezone' => 'Asia/Manila',
                'status' => 'active',
            ]);
        }

        return BiometricDevice::create([
            'name' => 'Main Entrance Scanner',
            'device_identifier' => 'DEV-MAIN-001',
            'work_location_id' => $workLocation->id,
            'status' => 'online',
            'is_active' => true,
            'last_seen_at' => now(),
        ]);
    }

    /**
     * Assign schedules to active employees who don't have one.
     *
     * @param  array<string, WorkSchedule>  $schedules
     */
    protected function assignSchedulesToEmployees(array $schedules): void
    {
        $this->command->info('Assigning schedules to employees...');

        // Get active employees without current schedule assignments
        $employees = Employee::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereDoesntHave('scheduleAssignments', function ($query) {
                $query->active();
            })
            ->get();

        if ($employees->isEmpty()) {
            $this->command->info('  All active employees already have schedule assignments.');

            return;
        }

        $scheduleArray = array_values($schedules);
        $assigned = 0;

        foreach ($employees as $index => $employee) {
            // Assign schedules in round-robin fashion
            $schedule = $scheduleArray[$index % count($scheduleArray)];

            EmployeeScheduleAssignment::create([
                'employee_id' => $employee->id,
                'work_schedule_id' => $schedule->id,
                'shift_name' => null,
                'effective_date' => now()->subMonth()->startOfMonth()->toDateString(),
                'end_date' => null,
            ]);

            $assigned++;
        }

        $this->command->info("  Assigned schedules to {$assigned} employees.");
    }

    /**
     * Generate attendance logs for employees with schedule assignments.
     */
    protected function generateAttendanceLogs(BiometricDevice $device): void
    {
        $this->command->info('Generating attendance logs...');

        // Get employees with active schedule assignments
        $employees = Employee::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereHas('scheduleAssignments', function ($query) {
                $query->active();
            })
            ->with(['scheduleAssignments' => function ($query) {
                $query->active()->with('workSchedule');
            }])
            ->get();

        if ($employees->isEmpty()) {
            $this->command->warn('  No employees with active schedule assignments found.');

            return;
        }

        // Generate logs for the last 2 weeks (10 working days)
        $startDate = now()->subWeeks(2)->startOfWeek();
        $endDate = now()->subDay();

        $logsCreated = 0;

        foreach ($employees as $employee) {
            $assignment = $employee->scheduleAssignments->first();
            $schedule = $assignment?->workSchedule;

            if ($schedule === null) {
                continue;
            }

            $currentDate = $startDate->copy();

            while ($currentDate->lte($endDate)) {
                // Skip weekends for most schedules
                if ($this->isWorkDay($schedule, $currentDate)) {
                    // Skip if logs already exist for this date
                    $existingLogs = AttendanceLog::where('employee_id', $employee->id)
                        ->whereDate('logged_at', $currentDate->toDateString())
                        ->exists();

                    if (! $existingLogs) {
                        $logsCreated += $this->createDayLogs($employee, $device, $schedule, $currentDate);
                    }
                }

                $currentDate->addDay();
            }
        }

        $this->command->info("  Created {$logsCreated} attendance log entries.");
    }

    /**
     * Check if a date is a work day for the given schedule.
     */
    protected function isWorkDay(WorkSchedule $schedule, Carbon $date): bool
    {
        $dayName = strtolower($date->format('l'));
        $config = $schedule->time_configuration;

        if ($schedule->schedule_type === ScheduleType::Fixed || $schedule->schedule_type === ScheduleType::Flexible) {
            $workDays = $config['work_days'] ?? ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];

            return in_array($dayName, $workDays);
        }

        // Default to weekdays
        return ! $date->isWeekend();
    }

    /**
     * Create attendance logs for a single day.
     */
    protected function createDayLogs(
        Employee $employee,
        BiometricDevice $device,
        WorkSchedule $schedule,
        Carbon $date
    ): int {
        $config = $schedule->time_configuration;

        // Get start and end time based on schedule type
        if ($schedule->schedule_type === ScheduleType::Flexible) {
            // For flexible, random start between earliest and latest window
            $earliestStart = Carbon::parse($date->toDateString().' '.($config['flexible_start_window']['earliest'] ?? '06:00'));
            $latestStart = Carbon::parse($date->toDateString().' '.($config['flexible_start_window']['latest'] ?? '10:00'));

            $startTime = $earliestStart->copy()->addMinutes(rand(0, $earliestStart->diffInMinutes($latestStart)));
            $requiredHours = $config['required_hours_per_day'] ?? 8;
            $endTime = $startTime->copy()->addHours($requiredHours)->addHour(); // +1 for lunch
            $breakStartTime = $startTime->copy()->addHours(4); // Break after 4 hours
        } else {
            $startTime = Carbon::parse($date->toDateString().' '.($config['start_time'] ?? '08:00'));
            $endTime = Carbon::parse($date->toDateString().' '.($config['end_time'] ?? '17:00'));
            $breakStartTime = Carbon::parse($date->toDateString().' '.($config['break']['start_time'] ?? '12:00'));
        }

        // Apply variance to simulate real attendance patterns
        $variance = $this->getAttendanceVariance();

        // Create clock-in log
        $clockInTime = $startTime->copy()->addMinutes($variance['in_variance']);

        AttendanceLog::create([
            'biometric_device_id' => $device->id,
            'employee_id' => $employee->id,
            'device_person_id' => (string) $employee->id,
            'device_record_id' => (string) rand(100000, 999999),
            'employee_code' => $employee->employee_number,
            'confidence' => fake()->randomFloat(2, 90, 99.99),
            'verify_status' => '1',
            'logged_at' => $clockInTime,
            'direction' => 'in',
            'person_name' => $employee->full_name,
            'captured_photo' => null,
            'raw_payload' => null,
        ]);

        $logsCreated = 1;

        // Create break logs (70% chance - not everyone logs breaks)
        if (rand(1, 100) <= 70) {
            $breakVariance = rand(-5, 10); // Break start variance
            $breakDuration = rand(45, 75); // Break duration 45-75 minutes

            $breakOutTime = $breakStartTime->copy()->addMinutes($breakVariance);
            $breakInTime = $breakOutTime->copy()->addMinutes($breakDuration);

            // Break out
            AttendanceLog::create([
                'biometric_device_id' => $device->id,
                'employee_id' => $employee->id,
                'device_person_id' => (string) $employee->id,
                'device_record_id' => (string) rand(100000, 999999),
                'employee_code' => $employee->employee_number,
                'confidence' => fake()->randomFloat(2, 90, 99.99),
                'verify_status' => '1',
                'logged_at' => $breakOutTime,
                'direction' => 'break_out',
                'person_name' => $employee->full_name,
                'captured_photo' => null,
                'raw_payload' => null,
            ]);
            $logsCreated++;

            // Break in (95% return from break)
            if (rand(1, 100) <= 95) {
                AttendanceLog::create([
                    'biometric_device_id' => $device->id,
                    'employee_id' => $employee->id,
                    'device_person_id' => (string) $employee->id,
                    'device_record_id' => (string) rand(100000, 999999),
                    'employee_code' => $employee->employee_number,
                    'confidence' => fake()->randomFloat(2, 90, 99.99),
                    'verify_status' => '1',
                    'logged_at' => $breakInTime,
                    'direction' => 'break_in',
                    'person_name' => $employee->full_name,
                    'captured_photo' => null,
                    'raw_payload' => null,
                ]);
                $logsCreated++;
            }
        }

        // Create clock-out log (90% chance - sometimes employees forget)
        if (rand(1, 100) <= 90) {
            $clockOutTime = $endTime->copy()->addMinutes($variance['out_variance']);

            AttendanceLog::create([
                'biometric_device_id' => $device->id,
                'employee_id' => $employee->id,
                'device_person_id' => (string) $employee->id,
                'device_record_id' => (string) rand(100000, 999999),
                'employee_code' => $employee->employee_number,
                'confidence' => fake()->randomFloat(2, 90, 99.99),
                'verify_status' => '1',
                'logged_at' => $clockOutTime,
                'direction' => 'out',
                'person_name' => $employee->full_name,
                'captured_photo' => null,
                'raw_payload' => null,
            ]);

            $logsCreated++;
        }

        return $logsCreated;
    }

    /**
     * Get random variance for attendance times to simulate real-world patterns.
     *
     * @return array{in_variance: int, out_variance: int}
     */
    protected function getAttendanceVariance(): array
    {
        $scenarios = [
            // 60% on time or early
            ['in_variance' => rand(-15, 5), 'out_variance' => rand(0, 30)],
            ['in_variance' => rand(-15, 5), 'out_variance' => rand(0, 30)],
            ['in_variance' => rand(-15, 5), 'out_variance' => rand(0, 30)],
            ['in_variance' => rand(-15, 5), 'out_variance' => rand(0, 30)],
            ['in_variance' => rand(-15, 5), 'out_variance' => rand(0, 30)],
            ['in_variance' => rand(-15, 5), 'out_variance' => rand(0, 30)],
            // 25% slightly late (5-15 mins)
            ['in_variance' => rand(5, 15), 'out_variance' => rand(0, 15)],
            ['in_variance' => rand(5, 15), 'out_variance' => rand(0, 15)],
            ['in_variance' => rand(5, 15), 'out_variance' => rand(-5, 5)],
            // 10% late (15-60 mins)
            ['in_variance' => rand(15, 60), 'out_variance' => rand(-10, 60)],
            // 5% very early and overtime
            ['in_variance' => rand(-30, -15), 'out_variance' => rand(60, 120)],
        ];

        return $scenarios[array_rand($scenarios)];
    }

    /**
     * Calculate DTR records from attendance logs.
     */
    protected function calculateDtrRecords(): void
    {
        $this->command->info('Calculating DTR records...');

        $calculationService = app(DtrCalculationService::class);

        // Get employees with schedule assignments
        $employees = Employee::query()
            ->where('employment_status', EmploymentStatus::Active)
            ->whereHas('scheduleAssignments', function ($query) {
                $query->active();
            })
            ->get();

        $startDate = now()->subWeeks(2)->startOfWeek();
        $endDate = now()->subDay();

        $recordsCreated = 0;

        foreach ($employees as $employee) {
            $records = $calculationService->calculateForDateRange(
                $employee,
                $startDate,
                $endDate
            );

            $recordsCreated += $records->count();
        }

        $this->command->info("  Created/updated {$recordsCreated} DTR records.");
    }
}
