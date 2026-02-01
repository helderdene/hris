<?php

namespace Database\Seeders;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\JobLevel;
use App\Enums\LocationType;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\SalaryStep;
use App\Models\Tenant;
use App\Models\WorkLocation;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Database\Seeder;

use function Laravel\Prompts\select;

class TenantSampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tenants = Tenant::all();

        if ($tenants->isEmpty()) {
            $this->command->error('No tenants found. Please create a tenant first.');

            return;
        }

        $tenantSlug = select(
            label: 'Which tenant do you want to seed?',
            options: $tenants->pluck('name', 'slug')->toArray(),
            default: 'test',
        );

        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $this->command->info("Seeding sample data for tenant: {$tenant->name} ({$tenant->slug})");

        // Switch to tenant database
        app(TenantDatabaseManager::class)->switchConnection($tenant);

        $this->seedWorkLocations();
        $this->seedDepartments();
        $this->seedSalaryGrades();
        $this->seedPositions();
        $this->seedEmployees();
        $this->seedPhilippineHolidays();

        $this->command->info('Sample data seeded successfully!');
    }

    protected function seedWorkLocations(): void
    {
        $this->command->info('Creating work locations...');

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
        $this->command->info('Creating departments...');

        // Executive
        $executive = Department::create([
            'name' => 'Executive Office',
            'code' => 'EXEC',
            'description' => 'Executive leadership and corporate governance',
            'status' => 'active',
        ]);

        // HR Department with sub-departments
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

        // Finance Department
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

        // IT Department
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

        // Operations
        $operations = Department::create([
            'name' => 'Operations',
            'code' => 'OPS',
            'description' => 'Business operations and process management',
            'status' => 'active',
        ]);

        // Sales & Marketing
        $sales = Department::create([
            'name' => 'Sales & Marketing',
            'code' => 'SALES',
            'description' => 'Revenue generation and brand management',
            'status' => 'active',
        ]);
    }

    protected function seedSalaryGrades(): void
    {
        $this->command->info('Creating salary grades...');

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

            // Create 5 steps for each grade
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
        $this->command->info('Creating positions...');

        $grades = SalaryGrade::all()->keyBy('name');

        $positions = [
            // Executive
            ['title' => 'Chief Executive Officer', 'code' => 'CEO-001', 'level' => JobLevel::Executive, 'grade' => 'Grade 8 - Executive'],
            ['title' => 'Chief Financial Officer', 'code' => 'CFO-001', 'level' => JobLevel::Executive, 'grade' => 'Grade 8 - Executive'],
            ['title' => 'Chief Technology Officer', 'code' => 'CTO-001', 'level' => JobLevel::Executive, 'grade' => 'Grade 8 - Executive'],

            // Directors
            ['title' => 'HR Director', 'code' => 'HRD-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'Finance Director', 'code' => 'FND-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'IT Director', 'code' => 'ITD-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'Operations Director', 'code' => 'OPD-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],
            ['title' => 'Sales Director', 'code' => 'SLD-001', 'level' => JobLevel::Director, 'grade' => 'Grade 7 - Director'],

            // Managers
            ['title' => 'HR Manager', 'code' => 'HRM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Recruitment Manager', 'code' => 'RCM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Payroll Manager', 'code' => 'PYM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Accounting Manager', 'code' => 'ACM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'IT Manager', 'code' => 'ITM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Development Manager', 'code' => 'DVM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Operations Manager', 'code' => 'OPM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],
            ['title' => 'Sales Manager', 'code' => 'SLM-001', 'level' => JobLevel::Manager, 'grade' => 'Grade 6 - Manager'],

            // Leads/Specialists
            ['title' => 'Senior Software Engineer', 'code' => 'SSE-001', 'level' => JobLevel::Lead, 'grade' => 'Grade 5 - Lead/Specialist'],
            ['title' => 'Team Lead - Development', 'code' => 'TLD-001', 'level' => JobLevel::Lead, 'grade' => 'Grade 5 - Lead/Specialist'],
            ['title' => 'Senior Accountant', 'code' => 'SAC-001', 'level' => JobLevel::Lead, 'grade' => 'Grade 5 - Lead/Specialist'],
            ['title' => 'HR Specialist', 'code' => 'HRS-001', 'level' => JobLevel::Lead, 'grade' => 'Grade 5 - Lead/Specialist'],

            // Senior
            ['title' => 'Software Engineer III', 'code' => 'SE3-001', 'level' => JobLevel::Senior, 'grade' => 'Grade 4 - Senior'],
            ['title' => 'Accountant III', 'code' => 'AC3-001', 'level' => JobLevel::Senior, 'grade' => 'Grade 4 - Senior'],
            ['title' => 'HR Officer III', 'code' => 'HO3-001', 'level' => JobLevel::Senior, 'grade' => 'Grade 4 - Senior'],
            ['title' => 'Sales Executive', 'code' => 'SLE-001', 'level' => JobLevel::Senior, 'grade' => 'Grade 4 - Senior'],

            // Mid
            ['title' => 'Software Engineer II', 'code' => 'SE2-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'Accountant II', 'code' => 'AC2-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'HR Officer II', 'code' => 'HO2-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'IT Support Specialist', 'code' => 'ITS-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],
            ['title' => 'Sales Representative', 'code' => 'SLR-001', 'level' => JobLevel::Mid, 'grade' => 'Grade 3 - Mid-Level'],

            // Junior
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
        $this->command->info('Creating employees...');

        $departments = Department::all()->keyBy('code');
        $positions = Position::all()->keyBy('code');
        $locations = WorkLocation::all();
        $hqLocation = $locations->where('code', 'HQ-MKT')->first();
        $bgcLocation = $locations->where('code', 'BR-BGC')->first();
        $cebuLocation = $locations->where('code', 'BR-CEB')->first();

        // Create CEO first
        $ceo = $this->createEmployee([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'email' => 'maria.santos@test.kasamahr.com',
            'employee_number' => 'EMP-000001',
            'department_id' => $departments['EXEC']->id,
            'position_id' => $positions['CEO-001']->id,
            'work_location_id' => $hqLocation->id,
            'hire_date' => '2015-01-15',
            'basic_salary' => 350000,
        ]);

        // Create Directors (reporting to CEO)
        $hrDirector = $this->createEmployee([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'email' => 'juan.delacruz@test.kasamahr.com',
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
            'email' => 'antonio.reyes@test.kasamahr.com',
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
            'email' => 'carmen.aquino@test.kasamahr.com',
            'employee_number' => 'EMP-000004',
            'department_id' => $departments['FIN']->id,
            'position_id' => $positions['FND-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $ceo->id,
            'hire_date' => '2016-09-01',
            'basic_salary' => 210000,
        ]);

        // Create IT Manager
        $itManager = $this->createEmployee([
            'first_name' => 'Roberto',
            'last_name' => 'Garcia',
            'email' => 'roberto.garcia@test.kasamahr.com',
            'employee_number' => 'EMP-000005',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['DVM-001']->id,
            'work_location_id' => $bgcLocation->id,
            'supervisor_id' => $itDirector->id,
            'hire_date' => '2018-01-15',
            'basic_salary' => 145000,
        ]);

        // Create HR Manager
        $hrManager = $this->createEmployee([
            'first_name' => 'Elena',
            'last_name' => 'Mendoza',
            'email' => 'elena.mendoza@test.kasamahr.com',
            'employee_number' => 'EMP-000006',
            'department_id' => $departments['HR']->id,
            'position_id' => $positions['HRM-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $hrDirector->id,
            'hire_date' => '2018-04-01',
            'basic_salary' => 135000,
        ]);

        // Create Senior Software Engineers
        $this->createEmployee([
            'first_name' => 'Michael',
            'last_name' => 'Tan',
            'email' => 'michael.tan@test.kasamahr.com',
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
            'email' => 'angela.lim@test.kasamahr.com',
            'employee_number' => 'EMP-000008',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SSE-001']->id,
            'work_location_id' => $cebuLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2019-05-15',
            'basic_salary' => 105000,
        ]);

        // Create Mid-level developers
        $this->createEmployee([
            'first_name' => 'Paolo',
            'last_name' => 'Villar',
            'email' => 'paolo.villar@test.kasamahr.com',
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
            'email' => 'diana.cruz@test.kasamahr.com',
            'employee_number' => 'EMP-000010',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SE2-001']->id,
            'work_location_id' => $cebuLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2021-07-15',
            'basic_salary' => 52000,
        ]);

        // Create Junior developers
        $this->createEmployee([
            'first_name' => 'Kevin',
            'last_name' => 'Ramos',
            'email' => 'kevin.ramos@test.kasamahr.com',
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
            'email' => 'patricia.ocampo@test.kasamahr.com',
            'employee_number' => 'EMP-000012',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SE1-001']->id,
            'work_location_id' => $bgcLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2024-06-01',
            'basic_salary' => 30000,
            'employment_type' => EmploymentType::Probationary,
        ]);

        // Create HR staff
        $this->createEmployee([
            'first_name' => 'Rose',
            'last_name' => 'Manalo',
            'email' => 'rose.manalo@test.kasamahr.com',
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
            'email' => 'mark.pascual@test.kasamahr.com',
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
            'email' => 'ana.bautista@test.kasamahr.com',
            'employee_number' => 'EMP-000015',
            'department_id' => $departments['HR-REC']->id,
            'position_id' => $positions['HO1-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $hrManager->id,
            'hire_date' => '2023-03-01',
            'basic_salary' => 28000,
        ]);

        // Create Finance staff
        $this->createEmployee([
            'first_name' => 'Ricardo',
            'last_name' => 'Fernandez',
            'email' => 'ricardo.fernandez@test.kasamahr.com',
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
            'email' => 'christina.yu@test.kasamahr.com',
            'employee_number' => 'EMP-000017',
            'department_id' => $departments['FIN-ACC']->id,
            'position_id' => $positions['AC2-001']->id,
            'work_location_id' => $hqLocation->id,
            'supervisor_id' => $financeDirector->id,
            'hire_date' => '2022-01-15',
            'basic_salary' => 45000,
        ]);

        // Add a resigned employee
        $this->createEmployee([
            'first_name' => 'Jose',
            'last_name' => 'Rivera',
            'email' => 'jose.rivera@test.kasamahr.com',
            'employee_number' => 'EMP-000018',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SE2-001']->id,
            'work_location_id' => $bgcLocation->id,
            'hire_date' => '2020-06-01',
            'basic_salary' => 55000,
            'employment_status' => EmploymentStatus::Resigned,
            'termination_date' => '2024-12-15',
        ]);

        // Add an intern
        $this->createEmployee([
            'first_name' => 'Miguel',
            'last_name' => 'Torres',
            'email' => 'miguel.torres@test.kasamahr.com',
            'employee_number' => 'EMP-000019',
            'department_id' => $departments['IT-DEV']->id,
            'position_id' => $positions['SE1-001']->id,
            'work_location_id' => $bgcLocation->id,
            'supervisor_id' => $itManager->id,
            'hire_date' => '2025-10-01',
            'basic_salary' => 18000,
            'employment_type' => EmploymentType::Intern,
        ]);

        // Create more employees using factory for variety
        Employee::factory()
            ->count(10)
            ->regular()
            ->create([
                'department_id' => $departments['OPS']->id,
                'work_location_id' => $hqLocation->id,
            ]);

        Employee::factory()
            ->count(5)
            ->create([
                'department_id' => $departments['SALES']->id,
                'work_location_id' => $cebuLocation->id,
            ]);

        // Assign department heads
        $this->command->info('Assigning department heads...');

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

    /**
     * Seed Philippine national holidays using the PhilippineHolidaySeeder.
     */
    protected function seedPhilippineHolidays(): void
    {
        $this->command->info('Creating Philippine national holidays...');
        $this->call(PhilippineHolidaySeeder::class);
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
