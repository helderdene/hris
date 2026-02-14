<?php

namespace App\Console\Commands;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Enums\LocationType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\ProficiencyLevel;
use App\Models\SalaryGrade;
use App\Models\SalaryStep;
use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Services\Tenant\TenantDatabaseManager;
use Database\Seeders\GovernmentContributionSeeder;
use Database\Seeders\PhilippineHolidaySeeder;
use Illuminate\Console\Command;

class SeedTenantSampleData extends Command
{
    protected $signature = 'tenant:seed-sample-data {tenant? : The tenant slug to seed data for}';

    protected $description = 'Seed sample data (departments, positions, employees) for a tenant';

    public function handle(): int
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('No tenants found. Please create a tenant first.');

            return self::FAILURE;
        }

        $tenantSlug = $this->argument('tenant');

        if (! $tenantSlug) {
            $tenantSlug = $this->choice(
                'Which tenant do you want to seed sample data for?',
                $tenants->pluck('name', 'slug')->toArray(),
                $tenants->first()?->slug
            );
        }

        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (! $tenant) {
            $this->error("Tenant with slug '{$tenantSlug}' not found.");
            $this->info('Available tenants: '.$tenants->pluck('slug')->implode(', '));

            return self::FAILURE;
        }

        $this->info("Seeding sample data for tenant: {$tenant->name} ({$tenant->slug})");

        // Switch to tenant database
        app(TenantDatabaseManager::class)->switchConnection($tenant);

        $this->seedWorkLocations();
        $this->seedDepartments();
        $this->seedSalaryGrades();
        $this->seedPositions();
        $this->seedEmployees();
        $this->seedPhilippineHolidays();
        $this->seedGovernmentContributions();
        $this->seedProficiencyLevels();

        $this->info('Sample data seeded successfully!');

        return self::SUCCESS;
    }

    protected function seedWorkLocations(): void
    {
        $this->info('Creating work locations...');

        if (WorkLocation::where('code', 'HQ-MKT')->exists()) {
            $this->info('Work locations already exist, skipping...');

            return;
        }

        WorkLocation::create([
            'name' => 'Main Office - Makati',
            'code' => 'HQ-MKT',
            'address' => '123 Ayala Avenue, Bel-Air',
            'city' => 'Makati City',
            'region' => 'Metro Manila',
            'country' => 'PH',
            'postal_code' => '1226',
            'location_type' => LocationType::Headquarters,
            'timezone' => 'Asia/Manila',
            'metadata' => ['phone' => '+63 2 8888 1234', 'capacity' => 200],
            'status' => 'active',
        ]);

        WorkLocation::create([
            'name' => 'BGC Branch',
            'code' => 'BR-BGC',
            'address' => '456 High Street, Bonifacio Global City',
            'city' => 'Taguig City',
            'region' => 'Metro Manila',
            'country' => 'PH',
            'postal_code' => '1634',
            'location_type' => LocationType::Branch,
            'timezone' => 'Asia/Manila',
            'metadata' => ['phone' => '+63 2 8888 5678', 'capacity' => 80],
            'status' => 'active',
        ]);

        WorkLocation::create([
            'name' => 'Cebu Office',
            'code' => 'BR-CEB',
            'address' => '789 IT Park, Lahug',
            'city' => 'Cebu City',
            'region' => 'Central Visayas',
            'country' => 'PH',
            'postal_code' => '6000',
            'location_type' => LocationType::Branch,
            'timezone' => 'Asia/Manila',
            'metadata' => ['phone' => '+63 32 888 9012', 'capacity' => 50],
            'status' => 'active',
        ]);
    }

    protected function seedDepartments(): void
    {
        $this->info('Creating departments...');

        if (Department::where('code', 'EXEC')->exists()) {
            $this->info('Departments already exist, skipping...');

            return;
        }

        $executive = Department::create([
            'name' => 'Executive Office',
            'code' => 'EXEC',
            'description' => 'Executive leadership and corporate governance',
            'status' => 'active',
        ]);

        $hr = Department::create([
            'name' => 'Human Resources',
            'code' => 'HR',
            'description' => 'Talent acquisition, employee relations, and HR operations',
            'status' => 'active',
        ]);

        Department::create([
            'name' => 'Recruitment',
            'code' => 'HR-REC',
            'parent_id' => $hr->id,
            'description' => 'Talent acquisition and hiring',
            'status' => 'active',
        ]);

        Department::create([
            'name' => 'Payroll & Benefits',
            'code' => 'HR-PAY',
            'parent_id' => $hr->id,
            'description' => 'Compensation and employee benefits administration',
            'status' => 'active',
        ]);

        $finance = Department::create([
            'name' => 'Finance',
            'code' => 'FIN',
            'description' => 'Financial planning, accounting, and treasury',
            'status' => 'active',
        ]);

        Department::create([
            'name' => 'Accounting',
            'code' => 'FIN-ACC',
            'parent_id' => $finance->id,
            'description' => 'General accounting and financial reporting',
            'status' => 'active',
        ]);

        $it = Department::create([
            'name' => 'Information Technology',
            'code' => 'IT',
            'description' => 'Technology infrastructure and software development',
            'status' => 'active',
        ]);

        Department::create([
            'name' => 'Software Development',
            'code' => 'IT-DEV',
            'parent_id' => $it->id,
            'description' => 'Application development and maintenance',
            'status' => 'active',
        ]);

        Department::create([
            'name' => 'IT Infrastructure',
            'code' => 'IT-INF',
            'parent_id' => $it->id,
            'description' => 'Network, servers, and technical support',
            'status' => 'active',
        ]);

        Department::create([
            'name' => 'Operations',
            'code' => 'OPS',
            'description' => 'Business operations and process management',
            'status' => 'active',
        ]);

        Department::create([
            'name' => 'Sales & Marketing',
            'code' => 'SALES',
            'description' => 'Revenue generation and brand management',
            'status' => 'active',
        ]);
    }

    protected function seedSalaryGrades(): void
    {
        $this->info('Creating salary grades...');

        if (SalaryGrade::where('name', 'Grade 1 - Entry Level')->exists()) {
            $this->info('Salary grades already exist, skipping...');

            return;
        }

        $grades = [
            ['name' => 'Grade 1 - Entry Level', 'min' => 18000, 'mid' => 22000, 'max' => 26000],
            ['name' => 'Grade 2 - Junior', 'min' => 25000, 'mid' => 32000, 'max' => 40000],
            ['name' => 'Grade 3 - Mid-Level', 'min' => 38000, 'mid' => 50000, 'max' => 65000],
            ['name' => 'Grade 4 - Senior', 'min' => 60000, 'mid' => 80000, 'max' => 100000],
            ['name' => 'Grade 5 - Lead/Specialist', 'min' => 90000, 'mid' => 115000, 'max' => 140000],
            ['name' => 'Grade 6 - Manager', 'min' => 120000, 'mid' => 155000, 'max' => 190000],
            ['name' => 'Grade 7 - Director', 'min' => 180000, 'mid' => 230000, 'max' => 280000],
            ['name' => 'Grade 8 - Executive', 'min' => 250000, 'mid' => 350000, 'max' => 450000],
        ];

        foreach ($grades as $grade) {
            $salaryGrade = SalaryGrade::create([
                'name' => $grade['name'],
                'minimum_salary' => $grade['min'],
                'midpoint_salary' => $grade['mid'],
                'maximum_salary' => $grade['max'],
                'currency' => 'PHP',
                'status' => 'active',
            ]);

            $increment = ($grade['max'] - $grade['min']) / 4;
            for ($i = 1; $i <= 5; $i++) {
                SalaryStep::create([
                    'salary_grade_id' => $salaryGrade->id,
                    'step_number' => $i,
                    'amount' => $grade['min'] + ($increment * ($i - 1)),
                ]);
            }
        }
    }

    protected function seedPositions(): void
    {
        $this->info('Creating positions...');

        if (Position::where('code', 'CEO-001')->exists()) {
            $this->info('Positions already exist, skipping...');

            return;
        }

        $grades = SalaryGrade::all()->keyBy('name');

        $positions = [
            ['title' => 'Chief Executive Officer', 'code' => 'CEO-001', 'level' => JobLevel::Executive, 'grade' => 'Grade 8 - Executive'],
            ['title' => 'Chief Financial Officer', 'code' => 'CFO-001', 'level' => JobLevel::Executive, 'grade' => 'Grade 8 - Executive'],
            ['title' => 'Chief Technology Officer', 'code' => 'CTO-001', 'level' => JobLevel::Executive, 'grade' => 'Grade 8 - Executive'],
            ['title' => 'HR Director', 'code' => 'HRD-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'Finance Director', 'code' => 'FND-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'IT Director', 'code' => 'ITD-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'Operations Director', 'code' => 'OPD-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'Sales Director', 'code' => 'SLD-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'HR Manager', 'code' => 'HRM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Recruitment Manager', 'code' => 'RCM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Payroll Manager', 'code' => 'PYM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Accounting Manager', 'code' => 'ACM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'IT Manager', 'code' => 'ITM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Development Manager', 'code' => 'DVM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Operations Manager', 'code' => 'OPM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Sales Manager', 'code' => 'SLM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Senior Software Engineer', 'code' => 'SSE-001', 'level' => JobLevel::Lead, 'grade' => 'Grade 5 - Lead/Specialist'],
            ['title' => 'Team Lead - Development', 'code' => 'TLD-001', 'level' => JobLevel::Lead, 'grade' => 'Grade 5 - Lead/Specialist'],
            ['title' => 'Senior Accountant', 'code' => 'SAC-001', 'level' => JobLevel::Lead, 'grade' => 'Grade 5 - Lead/Specialist'],
            ['title' => 'HR Specialist', 'code' => 'HRS-001', 'level' => JobLevel::Lead, 'grade' => 'Grade 5 - Lead/Specialist'],
            ['title' => 'Software Engineer III', 'code' => 'SE3-001', 'level' => JobLevel::Senior, 'grade' => 'Grade 4 - Senior'],
            ['title' => 'Accountant III', 'code' => 'AC3-001', 'level' => JobLevel::Senior, 'grade' => 'Grade 4 - Senior'],
            ['title' => 'HR Officer III', 'code' => 'HO3-001', 'level' => JobLevel::Senior, 'grade' => 'Grade 4 - Senior'],
            ['title' => 'Sales Executive', 'code' => 'SLE-001', 'level' => JobLevel::Senior, 'grade' => 'Grade 4 - Senior'],
            ['title' => 'Software Engineer II', 'code' => 'SE2-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'Accountant II', 'code' => 'AC2-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'HR Officer II', 'code' => 'HO2-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'IT Support Specialist', 'code' => 'ITS-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'Sales Representative', 'code' => 'SLR-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'Software Engineer I', 'code' => 'SE1-001', 'level' => JobLevel::Junior, 'grade' => 'Grade 2 - Junior'],
            ['title' => 'Accountant I', 'code' => 'AC1-001', 'level' => JobLevel::Junior, 'grade' => 'Grade 2 - Junior'],
            ['title' => 'HR Officer I', 'code' => 'HO1-001', 'level' => JobLevel::Junior, 'grade' => 'Grade 2 - Junior'],
            ['title' => 'IT Support Technician', 'code' => 'ITT-001', 'level' => JobLevel::Junior, 'grade' => 'Grade 2 - Junior'],
            ['title' => 'Administrative Assistant', 'code' => 'ADM-001', 'level' => JobLevel::Junior, 'grade' => 'Grade 2 - Junior'],
        ];

        foreach ($positions as $pos) {
            Position::create([
                'title' => $pos['title'],
                'code' => $pos['code'],
                'job_level' => $pos['level'],
                'salary_grade_id' => $grades[$pos['grade']]->id ?? null,
                'employment_type' => EmploymentType::Regular,
                'status' => 'active',
            ]);
        }
    }

    protected function seedEmployees(): void
    {
        $this->info('Creating employees...');

        if (Employee::where('employee_number', 'EMP-000001')->exists()) {
            $this->info('Employees already exist, skipping...');

            return;
        }

        $departments = Department::all()->keyBy('code');
        $positions = Position::all()->keyBy('code');
        $locations = WorkLocation::all();
        $hqLocation = $locations->where('code', 'HQ-MKT')->first();
        $bgcLocation = $locations->where('code', 'BR-BGC')->first();
        $cebuLocation = $locations->where('code', 'BR-CEB')->first();

        $ceo = $this->createEmployee([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@demo.kasamahr.com',
            'employee_number' => 'EMP-000001',
            'department_id' => $departments['EXEC']->id,
            'position_id' => $positions['CEO-001']->id,
            'work_location_id' => $hqLocation->id,
            'hire_date' => '2015-01-15',
            'basic_salary' => 350000,
        ]);

        $hrDirector = $this->createEmployee([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz@demo.kasamahr.com',
            'employee_number' => 'EMP-000002',
            'department_id' => $departments['HR']->id,
            'position_id' => $positions['HRD-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $ceo->id,
            'hire_date' => '2016-03-01',
            'basic_salary' => 220000,
        ]);

        $itDirector = $this->createEmployee([
            'first_name' => 'Antonio',
            'last_name' => 'Reyes',
            'email' => 'antonio.reyes@demo.kasamahr.com',
            'employee_number' => 'EMP-000003',
            'department_id' => $departments['IT']->id,
            'position_id' => $positions['ITD-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $ceo->id,
            'hire_date' => '2016-06-15',
            'basic_salary' => 230000,
        ]);

        $financeDirector = $this->createEmployee([
            'first_name' => 'Carmen',
            'last_name' => 'Aquino',
            'email' => 'carmen.aquino@demo.kasamahr.com',
            'employee_number' => 'EMP-000004',
            'department_id' => $departments['FIN']->id,
            'position_id' => $positions['FND-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $ceo->id,
            'hire_date' => '2016-09-01',
            'basic_salary' => 210000,
        ]);

        $itManager = $this->createEmployee([
            'first_name' => 'Roberto',
            'last_name' => 'Garcia',
            'email' => 'roberto.garcia@demo.kasamahr.com',
            'employee_number' => 'EMP-000005',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['DVM-001']->id,
            'work_location_id' => $bgcLocation->id,
            'supervisor_id' => $itDirector->id,
            'hire_date' => '2018-01-15',
            'basic_salary' => 145000,
        ]);

        $hrManager = $this->createEmployee([
            'first_name' => 'Elena',
            'last_name' => 'Mendoza',
            'email' => 'elena.mendoza@demo.kasamahr.com',
            'employee_number' => 'EMP-000006',
            'department_id' => $departments['HR']->id,
            'position_id' => $positions['HRM-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $hrDirector->id,
            'hire_date' => '2018-04-01',
            'basic_salary' => 135000,
        ]);

        $this->createEmployee([
            'first_name' => 'Michael',
            'last_name' => 'Tan',
            'email' => 'michael.tan@demo.kasamahr.com',
            'employee_number' => 'EMP-000007',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SSE-001']->id,
            'work_location_id' => $bgcLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2019-02-01',
            'basic_salary' => 110000,
        ]);

        $this->createEmployee([
            'first_name' => 'Angela',
            'last_name' => 'Lim',
            'email' => 'angela.lim@demo.kasamahr.com',
            'employee_number' => 'EMP-000008',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SSE-001']->id,
            'work_location_id' => $cebuLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2019-05-15',
            'basic_salary' => 105000,
        ]);

        $this->createEmployee([
            'first_name' => 'Paolo',
            'last_name' => 'Villar',
            'email' => 'paolo.villar@demo.kasamahr.com',
            'employee_number' => 'EMP-000009',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SE2-001']->id,
            'work_location_id' => $bgcLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2021-03-01',
            'basic_salary' => 55000,
        ]);

        $this->createEmployee([
            'first_name' => 'Diana',
            'last_name' => 'Cruz',
            'email' => 'diana.cruz@demo.kasamahr.com',
            'employee_number' => 'EMP-000010',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SE2-001']->id,
            'work_location_id' => $cebuLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2021-07-15',
            'basic_salary' => 52000,
        ]);

        $this->createEmployee([
            'first_name' => 'Kevin',
            'last_name' => 'Ramos',
            'email' => 'kevin.ramos@demo.kasamahr.com',
            'employee_number' => 'EMP-000011',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SE1-001']->id,
            'work_location_id' => $bgcLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2024-01-15',
            'basic_salary' => 32000,
            'employment_type' => EmploymentType::Probationary,
        ]);

        $this->createEmployee([
            'first_name' => 'Patricia',
            'last_name' => 'Ocampo',
            'email' => 'patricia.ocampo@demo.kasamahr.com',
            'employee_number' => 'EMP-000012',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SE1-001']->id,
            'work_location_id' => $bgcLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2024-06-01',
            'basic_salary' => 30000,
            'employment_type' => EmploymentType::Probationary,
        ]);

        $this->createEmployee([
            'first_name' => 'Rose',
            'last_name' => 'Manalo',
            'email' => 'rose.manalo@demo.kasamahr.com',
            'employee_number' => 'EMP-000013',
            'department_id' => $departments['HR-REC']->id,
            'position_id' => $positions['HRS-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $hrManager->id,
            'hire_date' => '2020-02-01',
            'basic_salary' => 95000,
        ]);

        $this->createEmployee([
            'first_name' => 'Mark',
            'last_name' => 'Pascual',
            'email' => 'mark.pascual@demo.kasamahr.com',
            'employee_number' => 'EMP-000014',
            'department_id' => $departments['HR-PAY']->id,
            'position_id' => $positions['HO2-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $hrManager->id,
            'hire_date' => '2021-08-15',
            'basic_salary' => 48000,
        ]);

        $this->createEmployee([
            'first_name' => 'Ana',
            'last_name' => 'Bautista',
            'email' => 'ana.bautista@demo.kasamahr.com',
            'employee_number' => 'EMP-000015',
            'department_id' => $departments['HR-REC']->id,
            'position_id' => $positions['HO1-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $hrManager->id,
            'hire_date' => '2023-03-01',
            'basic_salary' => 28000,
        ]);

        $this->createEmployee([
            'first_name' => 'Ricardo',
            'last_name' => 'Fernandez',
            'email' => 'ricardo.fernandez@demo.kasamahr.com',
            'employee_number' => 'EMP-000016',
            'department_id' => $departments['FIN-ACC']->id,
            'position_id' => $positions['SAC-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $financeDirector->id,
            'hire_date' => '2019-09-01',
            'basic_salary' => 92000,
        ]);

        $this->createEmployee([
            'first_name' => 'Christina',
            'last_name' => 'Yu',
            'email' => 'christina.yu@demo.kasamahr.com',
            'employee_number' => 'EMP-000017',
            'department_id' => $departments['FIN-ACC']->id,
            'position_id' => $positions['AC2-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $financeDirector->id,
            'hire_date' => '2022-01-15',
            'basic_salary' => 45000,
        ]);

        // Assign department heads
        $this->info('Assigning department heads...');
        $departments['EXEC']->update(['department_head_id' => $ceo->id]);
        $departments['HR']->update(['department_head_id' => $hrDirector->id]);
        $departments['HR-REC']->update(['department_head_id' => $hrManager->id]);
        $departments['HR-PAY']->update(['department_head_id' => $hrManager->id]);
        $departments['IT']->update(['department_head_id' => $itDirector->id]);
        $departments['IT-DEV']->update(['department_head_id' => $itManager->id]);
        $departments['IT-INF']->update(['department_head_id' => $itDirector->id]);
        $departments['FIN']->update(['department_head_id' => $financeDirector->id]);
        $departments['FIN-ACC']->update(['department_head_id' => $financeDirector->id]);
    }

    protected function seedPhilippineHolidays(): void
    {
        $this->info('Creating Philippine national holidays...');
        $this->call(PhilippineHolidaySeeder::class);
    }

    protected function seedGovernmentContributions(): void
    {
        $this->info('Creating government contribution tables...');
        $this->call(GovernmentContributionSeeder::class);
    }

    protected function seedProficiencyLevels(): void
    {
        $this->info('Creating proficiency levels...');

        if (ProficiencyLevel::where('level', 1)->exists()) {
            $this->info('Proficiency levels already exist, skipping...');

            return;
        }

        $levels = [
            [
                'level' => 1,
                'name' => 'Novice',
                'description' => 'Limited or no experience with this competency. Requires close supervision and guidance.',
                'behavioral_indicators' => [
                    'Needs detailed instructions for tasks',
                    'Requires frequent check-ins and feedback',
                    'Still developing basic understanding',
                ],
            ],
            [
                'level' => 2,
                'name' => 'Beginner',
                'description' => 'Basic understanding of the competency. Can perform routine tasks with some guidance.',
                'behavioral_indicators' => [
                    'Handles routine tasks with guidance',
                    'Asks appropriate questions when stuck',
                    'Can follow established procedures',
                ],
            ],
            [
                'level' => 3,
                'name' => 'Competent',
                'description' => 'Solid working knowledge. Can independently handle standard situations.',
                'behavioral_indicators' => [
                    'Works independently on standard tasks',
                    'Solves typical problems without assistance',
                    'Consistently meets expectations',
                ],
            ],
            [
                'level' => 4,
                'name' => 'Proficient',
                'description' => 'Advanced understanding and application. Handles complex situations effectively.',
                'behavioral_indicators' => [
                    'Handles complex or unusual situations',
                    'Mentors and coaches others',
                    'Recognized as a go-to resource',
                ],
            ],
            [
                'level' => 5,
                'name' => 'Expert',
                'description' => 'Deep expertise and mastery. Drives innovation and best practices.',
                'behavioral_indicators' => [
                    'Drives innovation and thought leadership',
                    'Develops new methods or approaches',
                    'Sought out as an authority on the topic',
                ],
            ],
        ];

        foreach ($levels as $level) {
            ProficiencyLevel::create($level);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createEmployee(array $data): Employee
    {
        $defaults = [
            'employment_type' => EmploymentType::Regular,
            'employment_status' => EmploymentStatus::Active,
            'pay_frequency' => 'semi-monthly',
            'gender' => fake()->randomElement(['male', 'female']),
            'civil_status' => fake()->randomElement(['single', 'married']),
            'nationality' => 'Filipino',
            'date_of_birth' => fake()->dateTimeBetween('-55 years', '-22 years'),
            'phone' => fake()->phoneNumber(),
            'tin' => fake()->numerify('###-###-###-###'),
            'sss_number' => fake()->numerify('##-#######-#'),
            'philhealth_number' => fake()->numerify('##-#########-#'),
            'pagibig_number' => fake()->numerify('####-####-####'),
            'address' => [
                'street' => fake()->streetAddress(),
                'barangay' => 'Barangay '.fake()->numberBetween(1, 100),
                'city' => fake()->city(),
                'province' => 'Metro Manila',
                'postal_code' => fake()->postcode(),
            ],
            'emergency_contact' => [
                'name' => fake()->name(),
                'relationship' => fake()->randomElement(['spouse', 'parent', 'sibling']),
                'phone' => fake()->phoneNumber(),
            ],
        ];

        return Employee::create(array_merge($defaults, $data));
    }
}
