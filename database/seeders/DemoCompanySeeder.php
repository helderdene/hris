<?php

namespace Database\Seeders;

use App\Enums\ApplicationSource;
use App\Enums\ApplicationStatus;
use App\Enums\CompetencyCategory;
use App\Enums\CompletionStatus;
use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Enums\CourseProviderType;
use App\Enums\CourseStatus;
use App\Enums\DevelopmentItemStatus;
use App\Enums\DevelopmentPlanStatus;
use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\EnrollmentStatus;
use App\Enums\GoalApprovalStatus;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\GoalVisibility;
use App\Enums\JobLevel;
use App\Enums\JobPostingStatus;
use App\Enums\JobRequisitionStatus;
use App\Enums\JobRequisitionUrgency;
use App\Enums\KeyResultMetricType;
use App\Enums\KpiAssignmentStatus;
use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Enums\OnboardingAssignedRole;
use App\Enums\OnboardingCategory;
use App\Enums\PayrollCycleType;
use App\Enums\PayType;
use App\Enums\PerformanceCycleInstanceStatus;
use App\Enums\PerformanceCycleType;
use App\Enums\SalaryDisplayOption;
use App\Enums\SessionStatus;
use App\Models\Announcement;
use App\Models\Candidate;
use App\Models\CandidateEducation;
use App\Models\CandidateWorkExperience;
use App\Models\Competency;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Department;
use App\Models\DevelopmentPlan;
use App\Models\DevelopmentPlanItem;
use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmployeeLoan;
use App\Models\Goal;
use App\Models\GoalKeyResult;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobRequisition;
use App\Models\KpiAssignment;
use App\Models\KpiTemplate;
use App\Models\OnboardingTemplate;
use App\Models\OnboardingTemplateItem;
use App\Models\PayrollCycle;
use App\Models\PerformanceCycle;
use App\Models\PerformanceCycleInstance;
use App\Models\PerformanceCycleParticipant;
use App\Models\Position;
use App\Models\PositionCompetency;
use App\Models\Tenant;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\select;

class DemoCompanySeeder extends Seeder
{
    protected Tenant $tenant;

    /**
     * Run the database seeds.
     *
     * Creates a complete demo company with realistic data across all modules.
     * Requires TenantSampleDataSeeder to be run first for foundation data.
     *
     * Usage: php artisan db:seed --class=DemoCompanySeeder
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->error('No tenants found. Please create a tenant first.');

            return;
        }

        $tenantSlug = select(
            label: 'Which tenant do you want to seed demo data for?',
            options: $tenants->pluck('name', 'slug')->toArray(),
            default: 'demo',
        );

        $this->tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $this->command->info("Seeding comprehensive demo data for tenant: {$this->tenant->name} ({$this->tenant->slug})");

        app(TenantDatabaseManager::class)->switchConnection($this->tenant);

        // Phase 1: Foundation data
        $this->command->info('--- Phase 1: Foundation Data ---');

        if (Employee::count() === 0) {
            $this->command->error('No employees found. Please run TenantSampleDataSeeder first:');
            $this->command->info('  php artisan db:seed --class=TenantSampleDataSeeder');

            return;
        }

        $this->command->info('Foundation employee data found ('.Employee::count().' employees).');

        $this->call(DocumentCategorySeeder::class);
        $this->call(PhilippineStatutoryLeaveSeeder::class);
        $this->call(GovernmentContributionSeeder::class);
        $this->call(ProficiencyLevelSeeder::class);
        $this->call(PreboardingTemplateSeeder::class);

        // Phase 2: Compensation & Payroll
        $this->command->info('--- Phase 2: Compensation & Payroll ---');
        $this->seedEmployeeCompensation();
        $this->seedPayrollCycles();
        $this->seedEmployeeLoans();

        // Phase 3: Leave Management
        $this->command->info('--- Phase 3: Leave Management ---');
        $leaveSeeder = new LeaveApplicationSampleDataSeeder;
        $leaveSeeder->setCommand($this->command);
        $leaveSeeder->run($this->tenant->slug);

        // Phase 5: Announcements
        $this->command->info('--- Phase 5: Announcements ---');
        $this->seedAnnouncements();

        // Phase 6: Performance Management
        $this->command->info('--- Phase 6: Performance Management ---');
        $this->seedCompetencies();
        $this->seedPerformanceCycles();
        $this->seedGoals();
        $this->seedKpiTemplatesAndAssignments();
        $this->seedDevelopmentPlans();

        // Phase 7: Training & Development
        $this->command->info('--- Phase 7: Training & Development ---');
        $this->seedCoursesAndTraining();

        // Phase 8: Onboarding Templates
        $this->command->info('--- Phase 8: Onboarding Templates ---');
        $this->seedOnboardingTemplates();

        // Phase 9: Recruitment
        $this->command->info('--- Phase 9: Recruitment ---');
        $this->seedRecruitment();

        $this->command->newLine();
        $this->command->info('Demo company data seeded successfully!');
    }

    protected function seedEmployeeCompensation(): void
    {
        $this->command->info('Creating employee compensation records...');

        $employees = Employee::where('employment_status', EmploymentStatus::Active)->get();

        foreach ($employees as $employee) {
            if ($employee->compensation !== null) {
                continue;
            }

            EmployeeCompensation::firstOrCreate(
                ['employee_id' => $employee->id],
                [
                    'basic_pay' => $employee->basic_salary ?? fake()->numberBetween(25000, 200000),
                    'currency' => 'PHP',
                    'pay_type' => PayType::SemiMonthly,
                    'effective_date' => $employee->hire_date,
                    'bank_name' => fake()->randomElement(['BDO', 'BPI', 'Metrobank', 'Landbank', 'UnionBank', 'Security Bank']),
                    'account_name' => $employee->first_name.' '.$employee->last_name,
                    'account_number' => fake()->numerify('##########'),
                    'account_type' => 'savings',
                ]
            );
        }

        $this->command->info("  Created compensation records for {$employees->count()} employees.");
    }

    protected function seedPayrollCycles(): void
    {
        $this->command->info('Creating payroll cycles...');

        PayrollCycle::firstOrCreate(
            ['code' => 'SM-REG'],
            [
                'name' => 'Semi-Monthly Regular',
                'cycle_type' => PayrollCycleType::SemiMonthly,
                'description' => 'Regular semi-monthly payroll for all employees',
                'status' => 'active',
                'is_default' => true,
                'cutoff_rules' => [
                    'first_half' => ['cutoff_start_day' => 1, 'cutoff_end_day' => 15, 'pay_day' => 25],
                    'second_half' => ['cutoff_start_day' => 16, 'cutoff_end_day' => -1, 'pay_day' => 10],
                ],
            ]
        );

        PayrollCycle::firstOrCreate(
            ['code' => 'MONTH-REG'],
            [
                'name' => 'Monthly Regular',
                'cycle_type' => PayrollCycleType::Monthly,
                'description' => 'Monthly payroll for executives and consultants',
                'status' => 'active',
                'is_default' => false,
                'cutoff_rules' => [
                    'cutoff_start_day' => 1,
                    'cutoff_end_day' => -1,
                    'pay_day' => 30,
                ],
            ]
        );

        PayrollCycle::firstOrCreate(
            ['code' => '13TH-MONTH'],
            [
                'name' => '13th Month Pay',
                'cycle_type' => PayrollCycleType::ThirteenthMonth,
                'description' => 'Annual 13th month pay for all employees',
                'status' => 'active',
                'is_default' => false,
                'cutoff_rules' => [
                    'cutoff_start_day' => 1,
                    'cutoff_end_day' => -1,
                    'pay_month' => 12,
                    'pay_day' => 24,
                ],
            ]
        );

        $this->command->info('  Created 3 payroll cycles.');
    }

    protected function seedEmployeeLoans(): void
    {
        $this->command->info('Creating employee loans...');

        $employees = Employee::where('employment_status', EmploymentStatus::Active)
            ->where('employment_type', EmploymentType::Regular)
            ->inRandomOrder()
            ->take(8)
            ->get();

        $loanCount = 0;

        foreach ($employees as $employee) {
            $empId = str_pad($employee->id, 3, '0', STR_PAD_LEFT);

            // SSS Salary Loan
            if (fake()->boolean(60)) {
                $loanCode = "SSS-SAL-E{$empId}";
                if (EmployeeLoan::withTrashed()->where('loan_code', $loanCode)->exists()) {
                    continue;
                }

                $principal = fake()->randomElement([20000, 30000, 40000, 50000]);
                $monthly = round($principal / 24, 2);
                $paid = $monthly * fake()->numberBetween(2, 12);

                EmployeeLoan::create([
                    'employee_id' => $employee->id,
                    'loan_type' => LoanType::SssSalary,
                    'loan_code' => $loanCode,
                    'reference_number' => fake()->numerify('SSS-####-######'),
                    'principal_amount' => $principal,
                    'interest_rate' => 0.1000,
                    'monthly_deduction' => $monthly,
                    'term_months' => 24,
                    'total_amount' => $principal,
                    'total_paid' => $paid,
                    'remaining_balance' => $principal - $paid,
                    'start_date' => now()->subMonths(fake()->numberBetween(2, 12)),
                    'expected_end_date' => now()->addMonths(fake()->numberBetween(6, 18)),
                    'status' => LoanStatus::Active,
                ]);
                $loanCount++;
            }

            // Pag-IBIG MPL
            if (fake()->boolean(40)) {
                $loanCode = "PAGIBIG-MPL-E{$empId}";
                if (EmployeeLoan::withTrashed()->where('loan_code', $loanCode)->exists()) {
                    continue;
                }

                $principal = fake()->randomElement([30000, 50000, 80000]);
                $monthly = round($principal / 24, 2);
                $paid = $monthly * fake()->numberBetween(1, 8);

                EmployeeLoan::create([
                    'employee_id' => $employee->id,
                    'loan_type' => LoanType::PagibigMpl,
                    'loan_code' => $loanCode,
                    'reference_number' => fake()->numerify('HDMF-####-######'),
                    'principal_amount' => $principal,
                    'interest_rate' => 0.0595,
                    'monthly_deduction' => $monthly,
                    'term_months' => 24,
                    'total_amount' => $principal,
                    'total_paid' => $paid,
                    'remaining_balance' => $principal - $paid,
                    'start_date' => now()->subMonths(fake()->numberBetween(1, 8)),
                    'expected_end_date' => now()->addMonths(fake()->numberBetween(12, 20)),
                    'status' => LoanStatus::Active,
                ]);
                $loanCount++;
            }

            // Company Cash Advance (some employees)
            if (fake()->boolean(20)) {
                $loanCode = "CA-E{$empId}";
                if (EmployeeLoan::withTrashed()->where('loan_code', $loanCode)->exists()) {
                    continue;
                }

                $principal = fake()->randomElement([5000, 10000, 15000]);
                $monthly = round($principal / 3, 2);

                EmployeeLoan::create([
                    'employee_id' => $employee->id,
                    'loan_type' => LoanType::CompanyCashAdvance,
                    'loan_code' => $loanCode,
                    'principal_amount' => $principal,
                    'interest_rate' => 0,
                    'monthly_deduction' => $monthly,
                    'term_months' => 3,
                    'total_amount' => $principal,
                    'total_paid' => 0,
                    'remaining_balance' => $principal,
                    'start_date' => now()->subDays(fake()->numberBetween(1, 30)),
                    'expected_end_date' => now()->addMonths(3),
                    'status' => LoanStatus::Active,
                    'notes' => fake()->randomElement(['Emergency medical expense', 'Home repair', 'Family needs']),
                ]);
                $loanCount++;
            }
        }

        $this->command->info("  Created {$loanCount} employee loans.");
    }

    protected function seedAnnouncements(): void
    {
        $this->command->info('Creating announcements...');

        $tenantId = $this->tenant->id;

        Announcement::create([
            'tenant_id' => $tenantId,
            'title' => 'Welcome to KasaMaHR Employee Portal',
            'body' => 'We are excited to welcome you to the new KasaMaHR Employee Self-Service Portal! Here you can manage your profile, view payslips, submit leave applications, track attendance, and much more. If you have any questions, please reach out to the HR department.',
            'published_at' => now()->subDays(30),
            'is_pinned' => true,
        ]);

        Announcement::create([
            'tenant_id' => $tenantId,
            'title' => 'Annual Company Outing - Save the Date!',
            'body' => 'Mark your calendars! Our annual company outing is scheduled for March 15-16, 2026 at a beach resort in Batangas. More details on transportation, accommodation, and activities will be shared soon. All regular employees are encouraged to join!',
            'published_at' => now()->subDays(5),
            'expires_at' => now()->addDays(45),
            'is_pinned' => false,
        ]);

        Announcement::create([
            'tenant_id' => $tenantId,
            'title' => 'Updated Work-From-Home Policy',
            'body' => "Effective February 1, 2026, we are updating our hybrid work arrangement. Employees may work from home up to 2 days per week, subject to manager approval. Core in-office days are Tuesday and Thursday. Please coordinate with your team leads for scheduling.\n\nKey changes:\n- Maximum 2 WFH days per week\n- Must be in office on Tue & Thu\n- Advance notice of 24 hours required\n- VPN must be used for all remote work",
            'published_at' => now()->subDays(12),
            'is_pinned' => false,
        ]);

        Announcement::create([
            'tenant_id' => $tenantId,
            'title' => 'Q1 2026 Town Hall Meeting',
            'body' => 'The quarterly town hall meeting will be held on January 31, 2026 at 3:00 PM in the main conference room (5th floor). Remote employees can join via the Teams link. The CEO will share company updates, financial highlights, and Q&A session.',
            'published_at' => now()->subDays(20),
            'expires_at' => now()->subDays(12),
            'is_pinned' => false,
        ]);

        Announcement::create([
            'tenant_id' => $tenantId,
            'title' => 'Health & Wellness: Free Flu Vaccination',
            'body' => 'As part of our employee wellness program, free flu vaccinations will be available at the clinic (Ground Floor, Main Office) from February 17-21, 2026. Walk-ins are welcome between 9 AM and 4 PM. Please bring your company ID.',
            'published_at' => now()->subDays(2),
            'expires_at' => now()->addDays(12),
            'is_pinned' => false,
        ]);

        $this->command->info('  Created 5 announcements.');
    }

    protected function seedCompetencies(): void
    {
        $this->command->info('Creating competencies and position mappings...');

        $competencies = [
            // Core competencies
            ['name' => 'Communication', 'code' => 'CORE-COM', 'category' => CompetencyCategory::Core, 'description' => 'Ability to express ideas clearly in written and verbal form, actively listen, and tailor messages for different audiences.'],
            ['name' => 'Teamwork & Collaboration', 'code' => 'CORE-TWK', 'category' => CompetencyCategory::Core, 'description' => 'Works effectively with others, contributes to team goals, and builds positive working relationships.'],
            ['name' => 'Integrity & Professionalism', 'code' => 'CORE-INT', 'category' => CompetencyCategory::Core, 'description' => 'Demonstrates honesty, ethical behavior, and maintains professional standards.'],
            ['name' => 'Adaptability', 'code' => 'CORE-ADP', 'category' => CompetencyCategory::Core, 'description' => 'Adjusts effectively to changing priorities, ambiguity, and new challenges.'],

            // Technical competencies
            ['name' => 'Software Development', 'code' => 'TECH-DEV', 'category' => CompetencyCategory::Technical, 'description' => 'Proficiency in designing, coding, testing, and maintaining software applications.'],
            ['name' => 'Data Analysis', 'code' => 'TECH-DAT', 'category' => CompetencyCategory::Technical, 'description' => 'Ability to collect, process, and interpret data to support business decisions.'],
            ['name' => 'Financial Accounting', 'code' => 'TECH-ACC', 'category' => CompetencyCategory::Technical, 'description' => 'Knowledge of accounting principles, financial reporting, and regulatory compliance.'],
            ['name' => 'HR Management', 'code' => 'TECH-HRM', 'category' => CompetencyCategory::Technical, 'description' => 'Understanding of HR processes including recruitment, compensation, labor law, and employee relations.'],

            // Leadership competencies
            ['name' => 'Strategic Thinking', 'code' => 'LEAD-STR', 'category' => CompetencyCategory::Leadership, 'description' => 'Ability to see the big picture, anticipate trends, and align actions with organizational goals.'],
            ['name' => 'People Management', 'code' => 'LEAD-PPL', 'category' => CompetencyCategory::Leadership, 'description' => 'Effectively leads, motivates, develops, and manages team members to achieve results.'],
            ['name' => 'Decision Making', 'code' => 'LEAD-DEC', 'category' => CompetencyCategory::Leadership, 'description' => 'Makes timely, informed decisions weighing risks, benefits, and organizational impact.'],

            // Interpersonal competencies
            ['name' => 'Customer Focus', 'code' => 'INTP-CUS', 'category' => CompetencyCategory::Interpersonal, 'description' => 'Understands and anticipates customer needs, delivers quality service, and builds lasting relationships.'],
            ['name' => 'Conflict Resolution', 'code' => 'INTP-CFR', 'category' => CompetencyCategory::Interpersonal, 'description' => 'Manages disagreements constructively and finds mutually beneficial solutions.'],

            // Analytical competencies
            ['name' => 'Problem Solving', 'code' => 'ANLT-PSL', 'category' => CompetencyCategory::Analytical, 'description' => 'Identifies root causes, generates creative solutions, and implements effective fixes.'],
            ['name' => 'Critical Thinking', 'code' => 'ANLT-CRT', 'category' => CompetencyCategory::Analytical, 'description' => 'Evaluates information objectively, identifies logical connections, and draws sound conclusions.'],
        ];

        $createdCompetencies = [];
        foreach ($competencies as $comp) {
            $createdCompetencies[$comp['code']] = Competency::firstOrCreate(
                ['code' => $comp['code']],
                array_merge($comp, ['is_active' => true])
            );
        }

        // Map competencies to positions (by job level)
        $positions = Position::all();
        $created = 0;

        foreach ($positions as $position) {
            // All positions get core competencies
            foreach (['CORE-COM', 'CORE-TWK', 'CORE-INT', 'CORE-ADP'] as $code) {
                $requiredLevel = match ($position->job_level) {
                    JobLevel::Junior => 2,
                    JobLevel::Mid => 3,
                    JobLevel::Senior, JobLevel::Lead => 4,
                    default => 3,
                };

                PositionCompetency::firstOrCreate(
                    ['position_id' => $position->id, 'competency_id' => $createdCompetencies[$code]->id],
                    [
                        'job_level' => $position->job_level,
                        'required_proficiency_level' => $requiredLevel,
                        'is_mandatory' => true,
                        'weight' => 15.00,
                    ]
                );
                $created++;
            }

            // Manager+ get leadership competencies
            if (in_array($position->job_level, [JobLevel::Manager, JobLevel::Director, JobLevel::Executive])) {
                foreach (['LEAD-STR', 'LEAD-PPL', 'LEAD-DEC'] as $code) {
                    PositionCompetency::firstOrCreate(
                        ['position_id' => $position->id, 'competency_id' => $createdCompetencies[$code]->id],
                        [
                            'job_level' => $position->job_level,
                            'required_proficiency_level' => $position->job_level === JobLevel::Executive ? 5 : 4,
                            'is_mandatory' => true,
                            'weight' => 20.00,
                        ]
                    );
                    $created++;
                }
            }

            // IT positions get software development competency
            if (str_contains(strtolower($position->title), 'software') || str_contains(strtolower($position->title), 'developer') || str_contains(strtolower($position->code), 'SE')) {
                PositionCompetency::firstOrCreate(
                    ['position_id' => $position->id, 'competency_id' => $createdCompetencies['TECH-DEV']->id],
                    [
                        'job_level' => $position->job_level,
                        'required_proficiency_level' => match ($position->job_level) {
                            JobLevel::Junior => 2,
                            JobLevel::Mid => 3,
                            JobLevel::Senior, JobLevel::Lead => 4,
                            default => 3,
                        },
                        'is_mandatory' => true,
                        'weight' => 25.00,
                    ]
                );
                $created++;
            }

            // Finance/Accounting positions
            if (str_contains(strtolower($position->title), 'account') || str_contains(strtolower($position->code), 'AC') || str_contains(strtolower($position->code), 'FN')) {
                PositionCompetency::firstOrCreate(
                    ['position_id' => $position->id, 'competency_id' => $createdCompetencies['TECH-ACC']->id],
                    [
                        'job_level' => $position->job_level,
                        'required_proficiency_level' => match ($position->job_level) {
                            JobLevel::Junior => 2,
                            JobLevel::Mid => 3,
                            default => 4,
                        },
                        'is_mandatory' => true,
                        'weight' => 25.00,
                    ]
                );
                $created++;
            }

            // HR positions
            if (str_contains(strtolower($position->title), 'hr') || str_contains(strtolower($position->code), 'HR') || str_contains(strtolower($position->code), 'RC')) {
                PositionCompetency::firstOrCreate(
                    ['position_id' => $position->id, 'competency_id' => $createdCompetencies['TECH-HRM']->id],
                    [
                        'job_level' => $position->job_level,
                        'required_proficiency_level' => match ($position->job_level) {
                            JobLevel::Junior => 2,
                            JobLevel::Mid => 3,
                            default => 4,
                        },
                        'is_mandatory' => true,
                        'weight' => 25.00,
                    ]
                );
                $created++;
            }
        }

        $this->command->info('  Created '.count($createdCompetencies)." competencies and {$created} position mappings.");
    }

    protected function seedPerformanceCycles(): void
    {
        $this->command->info('Creating performance cycles...');

        $annualCycle = PerformanceCycle::firstOrCreate(
            ['code' => 'ANNUAL-STD'],
            [
                'name' => 'Annual Performance Review',
                'cycle_type' => PerformanceCycleType::Annual,
                'description' => 'Comprehensive annual performance evaluation for all employees.',
                'status' => 'active',
                'is_default' => true,
            ]
        );

        $midYearCycle = PerformanceCycle::firstOrCreate(
            ['code' => 'MIDYEAR-STD'],
            [
                'name' => 'Mid-Year Check-In',
                'cycle_type' => PerformanceCycleType::MidYear,
                'description' => 'Semi-annual progress check and goal alignment.',
                'status' => 'active',
                'is_default' => false,
            ]
        );

        PerformanceCycle::firstOrCreate(
            ['code' => 'PROB-STD'],
            [
                'name' => 'Probationary Evaluation',
                'cycle_type' => PerformanceCycleType::Probationary,
                'description' => 'Performance evaluation for employees during their probationary period.',
                'status' => 'active',
                'is_default' => false,
            ]
        );

        // Create instances for current year
        $currentYear = now()->year;

        $annualInstance = PerformanceCycleInstance::firstOrCreate(
            ['performance_cycle_id' => $annualCycle->id, 'year' => $currentYear, 'instance_number' => 1],
            [
                'name' => "Annual Review {$currentYear}",
                'start_date' => "{$currentYear}-01-01",
                'end_date' => "{$currentYear}-12-31",
                'status' => PerformanceCycleInstanceStatus::Active,
                'activated_at' => now()->subDays(30),
                'enable_360_feedback' => true,
                'enable_peer_review' => true,
                'enable_direct_report_review' => false,
                'self_evaluation_deadline' => "{$currentYear}-11-15",
                'peer_review_deadline' => "{$currentYear}-11-30",
                'manager_review_deadline' => "{$currentYear}-12-15",
            ]
        );

        PerformanceCycleInstance::firstOrCreate(
            ['performance_cycle_id' => $midYearCycle->id, 'year' => $currentYear, 'instance_number' => 1],
            [
                'name' => "Mid-Year Check-In H1 {$currentYear}",
                'start_date' => "{$currentYear}-01-01",
                'end_date' => "{$currentYear}-06-30",
                'status' => PerformanceCycleInstanceStatus::Active,
                'activated_at' => now()->subDays(15),
                'self_evaluation_deadline' => "{$currentYear}-06-15",
                'manager_review_deadline' => "{$currentYear}-06-30",
            ]
        );

        // Assign participants to annual cycle
        $activeEmployees = Employee::where('employment_status', EmploymentStatus::Active)
            ->whereNotNull('supervisor_id')
            ->with('supervisor')
            ->get();

        $participantCount = 0;
        foreach ($activeEmployees as $employee) {
            PerformanceCycleParticipant::firstOrCreate(
                [
                    'performance_cycle_instance_id' => $annualInstance->id,
                    'employee_id' => $employee->id,
                ],
                [
                    'manager_id' => $employee->supervisor_id,
                    'is_excluded' => false,
                    'status' => 'pending',
                    'self_evaluation_due_date' => "{$currentYear}-11-15",
                    'manager_review_due_date' => "{$currentYear}-12-15",
                ]
            );
            $participantCount++;
        }

        $annualInstance->update(['employee_count' => $participantCount]);

        $this->command->info("  Created 3 cycles, 2 instances, {$participantCount} participants.");
    }

    protected function seedGoals(): void
    {
        $this->command->info('Creating employee goals...');

        $currentYear = now()->year;
        $annualInstance = PerformanceCycleInstance::where('name', "Annual Review {$currentYear}")->first();

        if (! $annualInstance) {
            $this->command->warn('  No annual instance found. Skipping goals.');

            return;
        }

        $participants = PerformanceCycleParticipant::where('performance_cycle_instance_id', $annualInstance->id)
            ->with('employee')
            ->take(10)
            ->get();

        $goalTemplates = [
            [
                'title' => 'Improve Team Productivity',
                'description' => 'Increase team output by streamlining processes and eliminating bottlenecks.',
                'type' => GoalType::OkrObjective,
                'priority' => GoalPriority::High,
                'key_results' => [
                    ['title' => 'Reduce average task completion time by 20%', 'metric_type' => KeyResultMetricType::Percentage, 'target' => 20, 'current' => 12],
                    ['title' => 'Implement 3 process automation improvements', 'metric_type' => KeyResultMetricType::Number, 'target' => 3, 'current' => 1],
                ],
            ],
            [
                'title' => 'Enhance Professional Skills',
                'description' => 'Complete relevant certifications and training to improve technical capabilities.',
                'type' => GoalType::SmartGoal,
                'priority' => GoalPriority::Medium,
                'key_results' => [
                    ['title' => 'Complete 2 professional certifications', 'metric_type' => KeyResultMetricType::Number, 'target' => 2, 'current' => 0],
                    ['title' => 'Attend 40 hours of training', 'metric_type' => KeyResultMetricType::Number, 'unit' => 'hours', 'target' => 40, 'current' => 16],
                ],
            ],
            [
                'title' => 'Increase Customer Satisfaction',
                'description' => 'Improve internal/external customer satisfaction scores through better service delivery.',
                'type' => GoalType::OkrObjective,
                'priority' => GoalPriority::High,
                'key_results' => [
                    ['title' => 'Achieve 90% satisfaction rating', 'metric_type' => KeyResultMetricType::Percentage, 'target' => 90, 'current' => 78],
                    ['title' => 'Reduce response time to under 4 hours', 'metric_type' => KeyResultMetricType::Number, 'unit' => 'hours', 'target' => 4, 'current' => 6],
                ],
            ],
            [
                'title' => 'Deliver Key Project Milestones',
                'description' => 'Successfully deliver all assigned project milestones on time and within budget.',
                'type' => GoalType::SmartGoal,
                'priority' => GoalPriority::Critical,
                'key_results' => [
                    ['title' => 'Complete all Q1-Q2 deliverables on time', 'metric_type' => KeyResultMetricType::Boolean, 'target' => 1, 'current' => 0],
                    ['title' => 'Stay within 95% of allocated budget', 'metric_type' => KeyResultMetricType::Percentage, 'target' => 95, 'current' => 92],
                ],
            ],
        ];

        $goalCount = 0;
        foreach ($participants as $participant) {
            $template = $goalTemplates[array_rand($goalTemplates)];

            $goal = Goal::create([
                'employee_id' => $participant->employee_id,
                'performance_cycle_instance_id' => $annualInstance->id,
                'goal_type' => $template['type'],
                'title' => $template['title'],
                'description' => $template['description'],
                'visibility' => fake()->randomElement([GoalVisibility::Private, GoalVisibility::Team]),
                'priority' => $template['priority'],
                'status' => fake()->randomElement([GoalStatus::Active, GoalStatus::Active, GoalStatus::Draft]),
                'approval_status' => GoalApprovalStatus::Approved,
                'start_date' => "{$currentYear}-01-01",
                'due_date' => "{$currentYear}-12-31",
                'progress_percentage' => fake()->numberBetween(10, 65),
                'weight' => 50.00,
            ]);

            foreach ($template['key_results'] as $order => $kr) {
                $achievement = $kr['target'] > 0 ? round(($kr['current'] / $kr['target']) * 100, 2) : 0;
                GoalKeyResult::create([
                    'goal_id' => $goal->id,
                    'title' => $kr['title'],
                    'metric_type' => $kr['metric_type'],
                    'metric_unit' => $kr['unit'] ?? null,
                    'target_value' => $kr['target'],
                    'starting_value' => 0,
                    'current_value' => $kr['current'],
                    'achievement_percentage' => $achievement,
                    'weight' => round(100 / count($template['key_results']), 2),
                    'status' => 'active',
                    'sort_order' => $order + 1,
                ]);
            }

            $goalCount++;
        }

        $this->command->info("  Created {$goalCount} goals with key results.");
    }

    protected function seedKpiTemplatesAndAssignments(): void
    {
        $this->command->info('Creating KPI templates and assignments...');

        $templates = [
            ['name' => 'Task Completion Rate', 'code' => 'KPI-TCR', 'metric_unit' => '%', 'default_target' => 95.00, 'default_weight' => 20.00, 'category' => 'Productivity'],
            ['name' => 'Quality Score', 'code' => 'KPI-QS', 'metric_unit' => '%', 'default_target' => 90.00, 'default_weight' => 20.00, 'category' => 'Quality'],
            ['name' => 'Attendance Rate', 'code' => 'KPI-ATT', 'metric_unit' => '%', 'default_target' => 97.00, 'default_weight' => 10.00, 'category' => 'Attendance'],
            ['name' => 'Training Hours Completed', 'code' => 'KPI-TRN', 'metric_unit' => 'hours', 'default_target' => 40.00, 'default_weight' => 10.00, 'category' => 'Development'],
            ['name' => 'Customer Satisfaction Score', 'code' => 'KPI-CSS', 'metric_unit' => '%', 'default_target' => 85.00, 'default_weight' => 15.00, 'category' => 'Service'],
            ['name' => 'Revenue Target Achievement', 'code' => 'KPI-REV', 'metric_unit' => 'PHP', 'default_target' => 100.00, 'default_weight' => 25.00, 'category' => 'Sales'],
        ];

        $createdTemplates = [];
        foreach ($templates as $tpl) {
            $createdTemplates[$tpl['code']] = KpiTemplate::firstOrCreate(
                ['code' => $tpl['code']],
                array_merge($tpl, ['is_active' => true])
            );
        }

        // Assign KPIs to participants
        $currentYear = now()->year;
        $annualInstance = PerformanceCycleInstance::where('name', "Annual Review {$currentYear}")->first();

        if (! $annualInstance) {
            return;
        }

        $participants = PerformanceCycleParticipant::where('performance_cycle_instance_id', $annualInstance->id)->take(10)->get();

        $assignmentCount = 0;
        foreach ($participants as $participant) {
            // Everyone gets Task Completion Rate and Attendance
            foreach (['KPI-TCR', 'KPI-ATT', 'KPI-QS'] as $code) {
                $tpl = $createdTemplates[$code];
                KpiAssignment::firstOrCreate(
                    ['kpi_template_id' => $tpl->id, 'performance_cycle_participant_id' => $participant->id],
                    [
                        'target_value' => $tpl->default_target,
                        'weight' => $tpl->default_weight,
                        'actual_value' => fake()->randomFloat(2, $tpl->default_target * 0.7, $tpl->default_target * 1.1),
                        'achievement_percentage' => fake()->randomFloat(2, 70, 110),
                        'status' => KpiAssignmentStatus::InProgress,
                    ]
                );
                $assignmentCount++;
            }
        }

        $this->command->info('  Created '.count($createdTemplates)." KPI templates and {$assignmentCount} assignments.");
    }

    protected function seedDevelopmentPlans(): void
    {
        $this->command->info('Creating development plans...');

        $employees = Employee::where('employment_status', EmploymentStatus::Active)
            ->whereNotNull('supervisor_id')
            ->inRandomOrder()
            ->take(6)
            ->get();

        $competencies = Competency::all();
        $planCount = 0;
        $createdByUserId = \App\Models\User::first()?->id ?? 1;

        foreach ($employees as $employee) {
            $status = fake()->randomElement([
                DevelopmentPlanStatus::InProgress,
                DevelopmentPlanStatus::InProgress,
                DevelopmentPlanStatus::Approved,
                DevelopmentPlanStatus::Draft,
            ]);

            $plan = DevelopmentPlan::create([
                'employee_id' => $employee->id,
                'title' => "{$employee->first_name}'s Development Plan ".now()->year,
                'description' => 'Professional development plan focused on strengthening core competencies and preparing for career advancement.',
                'status' => $status,
                'start_date' => now()->startOfYear(),
                'target_completion_date' => now()->endOfYear(),
                'manager_id' => $employee->supervisor_id,
                'created_by' => $createdByUserId,
                'career_path_notes' => fake()->randomElement([
                    'Targeting senior role within 12-18 months.',
                    'Building leadership skills for future team lead position.',
                    'Deepening technical expertise in current role.',
                    'Cross-functional skills development for broader career options.',
                ]),
            ]);

            // Create 2-3 development items
            $itemCount = fake()->numberBetween(2, 3);
            $selectedCompetencies = $competencies->random($itemCount);

            foreach ($selectedCompetencies as $competency) {
                DevelopmentPlanItem::create([
                    'development_plan_id' => $plan->id,
                    'competency_id' => $competency->id,
                    'title' => "Improve {$competency->name}",
                    'description' => "Focus on developing {$competency->name} through targeted learning activities and practical application.",
                    'current_level' => fake()->numberBetween(1, 3),
                    'target_level' => fake()->numberBetween(3, 5),
                    'priority' => fake()->randomElement([GoalPriority::High, GoalPriority::Medium]),
                    'status' => $status === DevelopmentPlanStatus::InProgress
                        ? fake()->randomElement([DevelopmentItemStatus::InProgress, DevelopmentItemStatus::NotStarted])
                        : DevelopmentItemStatus::NotStarted,
                    'progress_percentage' => $status === DevelopmentPlanStatus::InProgress ? fake()->numberBetween(10, 60) : 0,
                ]);
            }

            $planCount++;
        }

        $this->command->info("  Created {$planCount} development plans with items.");
    }

    protected function seedCoursesAndTraining(): void
    {
        $this->command->info('Creating courses, sessions, and enrollments...');

        // Course categories
        $categories = [];
        foreach ([
            ['name' => 'Technical Skills', 'code' => 'TECH', 'description' => 'Programming, tools, and technical knowledge'],
            ['name' => 'Soft Skills', 'code' => 'SOFT', 'description' => 'Communication, leadership, and interpersonal skills'],
            ['name' => 'Compliance', 'code' => 'COMP', 'description' => 'Regulatory and company policy training'],
            ['name' => 'Management', 'code' => 'MGMT', 'description' => 'Leadership and management development'],
        ] as $cat) {
            $categories[$cat['code']] = CourseCategory::firstOrCreate(
                ['code' => $cat['code']],
                array_merge($cat, ['is_active' => true])
            );
        }

        // Create courses
        $courses = [];
        $courseData = [
            [
                'title' => 'Data Privacy Act Compliance',
                'code' => 'CRS-DPA',
                'description' => 'Understanding the Philippine Data Privacy Act of 2012 (RA 10173) and its implications for our organization. Covers data subject rights, data processing principles, and breach notification requirements.',
                'delivery_method' => CourseDeliveryMethod::ELearning,
                'provider_type' => CourseProviderType::Internal,
                'duration_hours' => 4,
                'level' => CourseLevel::Beginner,
                'is_compliance' => true,
                'max_participants' => 50,
                'learning_objectives' => ['Understand DPA key principles', 'Identify personal data types', 'Know breach reporting procedures'],
            ],
            [
                'title' => 'Effective Communication Workshop',
                'code' => 'CRS-ECW',
                'description' => 'Practical workshop on improving workplace communication including written, verbal, and presentation skills.',
                'delivery_method' => CourseDeliveryMethod::InPerson,
                'provider_type' => CourseProviderType::Internal,
                'duration_hours' => 8,
                'level' => CourseLevel::Intermediate,
                'is_compliance' => false,
                'max_participants' => 25,
                'learning_objectives' => ['Master active listening', 'Improve email writing', 'Build confidence in presentations'],
            ],
            [
                'title' => 'Project Management Fundamentals',
                'code' => 'CRS-PMF',
                'description' => 'Introduction to project management methodology covering planning, execution, monitoring, and closing phases.',
                'delivery_method' => CourseDeliveryMethod::Blended,
                'provider_type' => CourseProviderType::External,
                'provider_name' => 'PM Institute Philippines',
                'duration_hours' => 16,
                'level' => CourseLevel::Intermediate,
                'is_compliance' => false,
                'max_participants' => 20,
                'cost' => 15000.00,
                'learning_objectives' => ['Create project plans', 'Manage project risks', 'Track milestones effectively'],
            ],
            [
                'title' => 'Leadership for New Managers',
                'code' => 'CRS-LNM',
                'description' => 'Comprehensive leadership development program for newly promoted or aspiring managers.',
                'delivery_method' => CourseDeliveryMethod::InPerson,
                'provider_type' => CourseProviderType::External,
                'provider_name' => 'Center for Leadership Excellence',
                'duration_hours' => 24,
                'level' => CourseLevel::Advanced,
                'is_compliance' => false,
                'max_participants' => 15,
                'cost' => 25000.00,
                'learning_objectives' => ['Develop coaching skills', 'Learn delegation techniques', 'Build high-performing teams'],
            ],
            [
                'title' => 'Cybersecurity Awareness',
                'code' => 'CRS-CSA',
                'description' => 'Essential cybersecurity awareness training covering phishing, password security, social engineering, and safe browsing practices.',
                'delivery_method' => CourseDeliveryMethod::Virtual,
                'provider_type' => CourseProviderType::Internal,
                'duration_hours' => 2,
                'level' => CourseLevel::Beginner,
                'is_compliance' => true,
                'max_participants' => 100,
                'learning_objectives' => ['Recognize phishing attempts', 'Create strong passwords', 'Report security incidents'],
            ],
            [
                'title' => 'Advanced Laravel Development',
                'code' => 'CRS-ALD',
                'description' => 'Deep dive into advanced Laravel patterns including service containers, event-driven architecture, queues, and testing strategies.',
                'delivery_method' => CourseDeliveryMethod::Virtual,
                'provider_type' => CourseProviderType::Internal,
                'duration_hours' => 16,
                'level' => CourseLevel::Advanced,
                'is_compliance' => false,
                'max_participants' => 15,
                'learning_objectives' => ['Master service container', 'Build event-driven systems', 'Write comprehensive tests'],
            ],
        ];

        foreach ($courseData as $data) {
            $courses[$data['code']] = Course::firstOrCreate(
                ['code' => $data['code']],
                array_merge($data, ['status' => CourseStatus::Published])
            );
        }

        // Attach categories to courses
        $courses['CRS-DPA']->categories()->syncWithoutDetaching([$categories['COMP']->id]);
        $courses['CRS-ECW']->categories()->syncWithoutDetaching([$categories['SOFT']->id]);
        $courses['CRS-PMF']->categories()->syncWithoutDetaching([$categories['MGMT']->id]);
        $courses['CRS-LNM']->categories()->syncWithoutDetaching([$categories['MGMT']->id]);
        $courses['CRS-CSA']->categories()->syncWithoutDetaching([$categories['COMP']->id]);
        $courses['CRS-ALD']->categories()->syncWithoutDetaching([$categories['TECH']->id]);

        // Create training sessions
        $sessions = [];

        // Past completed session
        $sessions[] = TrainingSession::firstOrCreate(
            ['course_id' => $courses['CRS-DPA']->id, 'start_date' => now()->subMonth()->startOfMonth()],
            [
                'title' => 'Data Privacy Act - January Batch',
                'end_date' => now()->subMonth()->startOfMonth(),
                'start_time' => '09:00',
                'end_time' => '13:00',
                'location' => 'Main Office - Training Room A',
                'status' => SessionStatus::Completed,
                'max_participants' => 50,
            ]
        );

        // Upcoming session
        $sessions[] = TrainingSession::firstOrCreate(
            ['course_id' => $courses['CRS-ECW']->id, 'start_date' => now()->addWeeks(2)->startOfWeek()],
            [
                'title' => 'Communication Workshop - March',
                'end_date' => now()->addWeeks(2)->startOfWeek(),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'location' => 'BGC Branch - Conference Room',
                'status' => SessionStatus::Scheduled,
                'max_participants' => 25,
            ]
        );

        $sessions[] = TrainingSession::firstOrCreate(
            ['course_id' => $courses['CRS-PMF']->id, 'start_date' => now()->addMonth()->startOfWeek()],
            [
                'title' => 'PM Fundamentals - Q1 2026',
                'end_date' => now()->addMonth()->startOfWeek()->addDay(),
                'start_time' => '09:00',
                'end_time' => '17:00',
                'location' => 'PM Institute Philippines Training Center',
                'status' => SessionStatus::Scheduled,
                'max_participants' => 20,
            ]
        );

        $sessions[] = TrainingSession::firstOrCreate(
            ['course_id' => $courses['CRS-CSA']->id, 'start_date' => now()->addWeeks(3)],
            [
                'title' => 'Cybersecurity Awareness - All Staff',
                'end_date' => now()->addWeeks(3),
                'start_time' => '14:00',
                'end_time' => '16:00',
                'virtual_link' => 'https://teams.microsoft.com/meet/cybersec-training-2026',
                'status' => SessionStatus::Scheduled,
                'max_participants' => 100,
            ]
        );

        // Create enrollments
        $activeEmployees = Employee::where('employment_status', EmploymentStatus::Active)
            ->inRandomOrder()
            ->take(15)
            ->get();

        $enrollmentCount = 0;
        foreach ($sessions as $session) {
            $enrollees = $activeEmployees->random(min(fake()->numberBetween(3, 8), $activeEmployees->count()));

            foreach ($enrollees as $enrollee) {
                $existing = TrainingEnrollment::where('training_session_id', $session->id)
                    ->where('employee_id', $enrollee->id)
                    ->exists();

                if ($existing) {
                    continue;
                }

                $isCompleted = $session->status === SessionStatus::Completed;
                $status = $isCompleted
                    ? fake()->randomElement([EnrollmentStatus::Attended, EnrollmentStatus::Attended, EnrollmentStatus::NoShow])
                    : EnrollmentStatus::Confirmed;

                TrainingEnrollment::create([
                    'training_session_id' => $session->id,
                    'employee_id' => $enrollee->id,
                    'status' => $status,
                    'enrolled_at' => $session->start_date->subDays(fake()->numberBetween(5, 20)),
                    'attended_at' => ($status === EnrollmentStatus::Attended) ? $session->start_date : null,
                    'completion_status' => ($status === EnrollmentStatus::Attended) ? CompletionStatus::Completed : null,
                    'assessment_score' => ($status === EnrollmentStatus::Attended) ? fake()->randomFloat(2, 75, 100) : null,
                ]);
                $enrollmentCount++;
            }
        }

        $this->command->info('  Created '.count($courses).' courses, '.count($sessions)." sessions, {$enrollmentCount} enrollments.");
    }

    protected function seedOnboardingTemplates(): void
    {
        $this->command->info('Creating onboarding templates...');

        $template = OnboardingTemplate::firstOrCreate(
            ['name' => 'Standard New Hire Onboarding'],
            [
                'description' => 'Comprehensive onboarding checklist for new employees covering IT setup, orientation, and initial training.',
                'is_default' => true,
                'is_active' => true,
            ]
        );

        $items = [
            ['category' => OnboardingCategory::Provisioning, 'name' => 'Create company email account', 'assigned_role' => OnboardingAssignedRole::IT, 'is_required' => true, 'sort_order' => 1, 'due_days_offset' => -1],
            ['category' => OnboardingCategory::Provisioning, 'name' => 'Set up Active Directory / SSO access', 'assigned_role' => OnboardingAssignedRole::IT, 'is_required' => true, 'sort_order' => 2, 'due_days_offset' => -1],
            ['category' => OnboardingCategory::Provisioning, 'name' => 'Configure HRIS system access', 'assigned_role' => OnboardingAssignedRole::IT, 'is_required' => true, 'sort_order' => 3, 'due_days_offset' => 0],
            ['category' => OnboardingCategory::Provisioning, 'name' => 'Provision project management tools', 'assigned_role' => OnboardingAssignedRole::IT, 'is_required' => false, 'sort_order' => 4, 'due_days_offset' => 1],
            ['category' => OnboardingCategory::Equipment, 'name' => 'Issue laptop/workstation', 'assigned_role' => OnboardingAssignedRole::IT, 'is_required' => true, 'sort_order' => 5, 'due_days_offset' => 0],
            ['category' => OnboardingCategory::Equipment, 'name' => 'Issue company ID and building access card', 'assigned_role' => OnboardingAssignedRole::Admin, 'is_required' => true, 'sort_order' => 6, 'due_days_offset' => 0],
            ['category' => OnboardingCategory::Equipment, 'name' => 'Assign workstation/desk', 'assigned_role' => OnboardingAssignedRole::Admin, 'is_required' => true, 'sort_order' => 7, 'due_days_offset' => -1],
            ['category' => OnboardingCategory::Orientation, 'name' => 'Company overview and values presentation', 'assigned_role' => OnboardingAssignedRole::HR, 'is_required' => true, 'sort_order' => 8, 'due_days_offset' => 0],
            ['category' => OnboardingCategory::Orientation, 'name' => 'Office tour and team introductions', 'assigned_role' => OnboardingAssignedRole::HR, 'is_required' => true, 'sort_order' => 9, 'due_days_offset' => 0],
            ['category' => OnboardingCategory::Orientation, 'name' => 'Review employee handbook and policies', 'assigned_role' => OnboardingAssignedRole::HR, 'is_required' => true, 'sort_order' => 10, 'due_days_offset' => 1],
            ['category' => OnboardingCategory::Orientation, 'name' => 'Benefits enrollment walkthrough', 'assigned_role' => OnboardingAssignedRole::HR, 'is_required' => true, 'sort_order' => 11, 'due_days_offset' => 3],
            ['category' => OnboardingCategory::Training, 'name' => 'Complete Data Privacy Act training', 'assigned_role' => OnboardingAssignedRole::HR, 'is_required' => true, 'sort_order' => 12, 'due_days_offset' => 5],
            ['category' => OnboardingCategory::Training, 'name' => 'Complete Cybersecurity Awareness training', 'assigned_role' => OnboardingAssignedRole::IT, 'is_required' => true, 'sort_order' => 13, 'due_days_offset' => 5],
            ['category' => OnboardingCategory::Training, 'name' => 'Role-specific training with supervisor', 'assigned_role' => OnboardingAssignedRole::HR, 'is_required' => true, 'sort_order' => 14, 'due_days_offset' => 10],
        ];

        foreach ($items as $item) {
            OnboardingTemplateItem::firstOrCreate(
                [
                    'onboarding_template_id' => $template->id,
                    'name' => $item['name'],
                ],
                array_merge($item, ['onboarding_template_id' => $template->id])
            );
        }

        $this->command->info('  Created onboarding template with '.count($items).' items.');
    }

    protected function seedRecruitment(): void
    {
        $this->command->info('Creating job requisitions, postings, and candidates...');

        $departments = Department::all()->keyBy('code');
        $positions = Position::all()->keyBy('code');
        $hrDirector = Employee::whereHas('position', fn ($q) => $q->where('code', 'HRD-001'))->first();
        $itDirector = Employee::whereHas('position', fn ($q) => $q->where('code', 'ITD-001'))->first();

        // Job Requisitions
        $requisitions = [];

        if ($positions->has('SE2-001') && $departments->has('IT-DEV')) {
            $requisitions[] = JobRequisition::create([
                'position_id' => $positions['SE2-001']->id,
                'department_id' => $departments['IT-DEV']->id,
                'requested_by_employee_id' => $itDirector?->id,
                'headcount' => 2,
                'employment_type' => EmploymentType::Regular,
                'salary_range_min' => 38000,
                'salary_range_max' => 55000,
                'justification' => 'Expanding the development team to support new product features and growing client base. Current team is at capacity with existing projects.',
                'urgency' => JobRequisitionUrgency::High,
                'preferred_start_date' => now()->addMonth()->startOfMonth(),
                'requirements' => [
                    '2+ years PHP/Laravel experience',
                    'Vue.js or React proficiency',
                    'MySQL experience',
                    'REST API development',
                    'Git workflow familiarity',
                ],
                'status' => JobRequisitionStatus::Approved,
                'current_approval_level' => 1,
                'total_approval_levels' => 1,
                'submitted_at' => now()->subDays(15),
                'approved_at' => now()->subDays(10),
            ]);
        }

        if ($positions->has('HO1-001') && $departments->has('HR-REC')) {
            $requisitions[] = JobRequisition::create([
                'position_id' => $positions['HO1-001']->id,
                'department_id' => $departments['HR-REC']->id,
                'requested_by_employee_id' => $hrDirector?->id,
                'headcount' => 1,
                'employment_type' => EmploymentType::Regular,
                'salary_range_min' => 25000,
                'salary_range_max' => 35000,
                'justification' => 'Replacement for resigned HR staff member. Need to maintain adequate HR support for growing workforce.',
                'urgency' => JobRequisitionUrgency::Normal,
                'preferred_start_date' => now()->addMonths(2)->startOfMonth(),
                'requirements' => [
                    "Bachelor's degree in HR or related field",
                    '1+ year HR experience preferred',
                    'Excellent communication skills',
                    'Proficiency in HRIS systems',
                ],
                'status' => JobRequisitionStatus::Approved,
                'current_approval_level' => 1,
                'total_approval_levels' => 1,
                'submitted_at' => now()->subDays(20),
                'approved_at' => now()->subDays(14),
            ]);
        }

        if ($positions->has('AC1-001') && $departments->has('FIN-ACC')) {
            $requisitions[] = JobRequisition::create([
                'position_id' => $positions['AC1-001']->id,
                'department_id' => $departments['FIN-ACC']->id,
                'headcount' => 1,
                'employment_type' => EmploymentType::Probationary,
                'salary_range_min' => 25000,
                'salary_range_max' => 33000,
                'justification' => 'Additional headcount to support expanding accounting operations.',
                'urgency' => JobRequisitionUrgency::Low,
                'preferred_start_date' => now()->addMonths(3)->startOfMonth(),
                'status' => JobRequisitionStatus::Pending,
                'current_approval_level' => 1,
                'total_approval_levels' => 1,
                'submitted_at' => now()->subDays(3),
            ]);
        }

        // Job Postings (from approved requisitions)
        $postings = [];
        foreach ($requisitions as $req) {
            if ($req->status !== JobRequisitionStatus::Approved) {
                continue;
            }

            $position = Position::find($req->position_id);
            $department = Department::find($req->department_id);

            $postings[] = JobPosting::create([
                'job_requisition_id' => $req->id,
                'department_id' => $req->department_id,
                'position_id' => $req->position_id,
                'created_by_employee_id' => $hrDirector?->id ?? $req->requested_by_employee_id,
                'title' => $position->title,
                'description' => "We are looking for a talented {$position->title} to join our {$department->name} team. This is an exciting opportunity to work on challenging projects in a collaborative environment.\n\nAs a {$position->title}, you will be responsible for contributing to our team's success through your expertise and dedication.",
                'requirements' => implode("\n", $req->requirements ?? []),
                'benefits' => "- Competitive salary package\n- HMO coverage (employee + 2 dependents)\n- 15 vacation leaves + 10 sick leaves\n- 13th month pay\n- Performance bonuses\n- Training & development support\n- Hybrid work arrangement",
                'employment_type' => $req->employment_type,
                'location' => 'Makati City / BGC, Taguig',
                'salary_display_option' => SalaryDisplayOption::RangeOnly,
                'salary_range_min' => $req->salary_range_min,
                'salary_range_max' => $req->salary_range_max,
                'application_instructions' => 'Please submit your resume and a brief cover letter. Shortlisted candidates will be contacted for an initial phone screening.',
                'status' => JobPostingStatus::Published,
                'published_at' => now()->subDays(fake()->numberBetween(3, 10)),
            ]);
        }

        // Create candidates
        $candidates = Candidate::factory()->count(15)->create();

        foreach ($candidates as $candidate) {
            CandidateEducation::factory()
                ->count(fake()->numberBetween(1, 2))
                ->for($candidate)
                ->create();

            CandidateWorkExperience::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($candidate)
                ->create();
        }

        // Create applications
        if (! empty($postings)) {
            $statuses = [
                ApplicationStatus::Applied,
                ApplicationStatus::Applied,
                ApplicationStatus::Screening,
                ApplicationStatus::Screening,
                ApplicationStatus::Interview,
                ApplicationStatus::Assessment,
                ApplicationStatus::Rejected,
            ];

            $applicants = $candidates->random(min(10, $candidates->count()));

            foreach ($applicants as $applicant) {
                $posting = $postings[array_rand($postings)];

                JobApplication::factory()
                    ->for($applicant, 'candidate')
                    ->for($posting, 'jobPosting')
                    ->withStatus(fake()->randomElement($statuses))
                    ->create([
                        'source' => ApplicationSource::CareersPage,
                    ]);
            }
        }

        $this->command->info('  Created '.count($requisitions).' requisitions, '.count($postings).' postings, '.$candidates->count().' candidates.');
    }
}
