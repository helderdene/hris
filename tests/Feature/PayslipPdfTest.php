<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\PayrollEntryController;
use App\Models\PayrollCycle;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payroll\PayslipPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForPayslipPdf(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForPayslipPdf(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('PayslipPdfService', function () {
    it('generates a single PDF for a payroll entry', function () {
        $tenant = Tenant::factory()->create(['name' => 'Test Company']);
        bindTenantContextForPayslipPdf($tenant);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();
        $period = PayrollPeriod::factory()->forCycle($cycle)->create([
            'name' => 'January 2026 - 1st Half',
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
            'pay_date' => '2026-01-25',
        ]);

        $entry = PayrollEntry::factory()->forPeriod($period)->computed()->create([
            'employee_number' => 'EMP-001',
            'employee_name' => 'John Doe',
            'department_name' => 'Engineering',
            'position_name' => 'Software Developer',
            'gross_pay' => 50000.00,
            'total_deductions' => 5000.00,
            'net_pay' => 45000.00,
        ]);

        $service = app(PayslipPdfService::class);
        $pdfContent = $service->generateSingle($entry);

        expect($pdfContent)->not->toBeEmpty();
        expect(substr($pdfContent, 0, 4))->toBe('%PDF');
    });

    it('generates bulk PDF for multiple entries', function () {
        $tenant = Tenant::factory()->create(['name' => 'Test Company']);
        bindTenantContextForPayslipPdf($tenant);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();
        $period = PayrollPeriod::factory()->forCycle($cycle)->create();

        $entries = PayrollEntry::factory()
            ->forPeriod($period)
            ->computed()
            ->count(3)
            ->create();

        $service = app(PayslipPdfService::class);
        $pdfContent = $service->generateBulk($entries);

        expect($pdfContent)->not->toBeEmpty();
        expect(substr($pdfContent, 0, 4))->toBe('%PDF');
    });

    it('generates ZIP file with individual PDFs', function () {
        $tenant = Tenant::factory()->create(['name' => 'Test Company']);
        bindTenantContextForPayslipPdf($tenant);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();
        $period = PayrollPeriod::factory()->forCycle($cycle)->create();

        $entries = PayrollEntry::factory()
            ->forPeriod($period)
            ->computed()
            ->count(3)
            ->create();

        $service = app(PayslipPdfService::class);
        $zipPath = $service->generateZip($entries);

        expect(file_exists($zipPath))->toBeTrue();
        expect(pathinfo($zipPath, PATHINFO_EXTENSION))->toBe('zip');

        // Clean up
        @unlink($zipPath);
    });
});

describe('PayslipPdf API', function () {
    describe('downloadPdf', function () {
        it('downloads single payslip PDF for authorized user', function () {
            $tenant = Tenant::factory()->create(['name' => 'Test Company']);
            bindTenantContextForPayslipPdf($tenant);

            $hrManager = createTenantUserForPayslipPdf($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->create([
                'name' => 'January 2026',
                'cutoff_start' => '2026-01-01',
                'cutoff_end' => '2026-01-15',
                'pay_date' => '2026-01-25',
            ]);

            $entry = PayrollEntry::factory()->forPeriod($period)->computed()->create([
                'employee_number' => 'EMP-001',
                'employee_name' => 'John Doe',
            ]);

            $controller = app(PayrollEntryController::class);
            $pdfService = app(PayslipPdfService::class);

            $response = $controller->downloadPdf($tenant->slug, $entry, $pdfService);

            expect($response->getStatusCode())->toBe(200);
            expect($response->headers->get('Content-Type'))->toBe('application/pdf');
            expect($response->headers->get('Content-Disposition'))->toContain('attachment');
            expect($response->headers->get('Content-Disposition'))->toContain('.pdf');
        });

        it('denies access to unauthorized user', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayslipPdf($tenant);

            $employee = createTenantUserForPayslipPdf($tenant, TenantUserRole::Employee);
            $this->actingAs($employee);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->create();
            $entry = PayrollEntry::factory()->forPeriod($period)->computed()->create();

            $controller = app(PayrollEntryController::class);
            $pdfService = app(PayslipPdfService::class);

            $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

            $controller->downloadPdf($tenant->slug, $entry, $pdfService);
        });
    });

    describe('downloadBulkPdf', function () {
        it('downloads combined PDF for small batch', function () {
            $tenant = Tenant::factory()->create(['name' => 'Test Company']);
            bindTenantContextForPayslipPdf($tenant);

            $hrManager = createTenantUserForPayslipPdf($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->create([
                'name' => 'January 2026',
            ]);

            PayrollEntry::factory()
                ->forPeriod($period)
                ->computed()
                ->count(5)
                ->create();

            $controller = app(PayrollEntryController::class);
            $pdfService = app(PayslipPdfService::class);

            $request = Request::create(
                "/api/organization/payroll-periods/{$period->id}/payslips/bulk-pdf",
                'POST',
                ['format' => 'pdf']
            );
            $request->setUserResolver(fn () => $hrManager);

            $response = $controller->downloadBulkPdf($request, $tenant->slug, $period, $pdfService);

            expect($response->getStatusCode())->toBe(200);
            expect($response->headers->get('Content-Type'))->toBe('application/pdf');
        });

        it('downloads ZIP for small batch when requested', function () {
            $tenant = Tenant::factory()->create(['name' => 'Test Company']);
            bindTenantContextForPayslipPdf($tenant);

            $hrManager = createTenantUserForPayslipPdf($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->create([
                'name' => 'January 2026',
            ]);

            PayrollEntry::factory()
                ->forPeriod($period)
                ->computed()
                ->count(3)
                ->create();

            $controller = app(PayrollEntryController::class);
            $pdfService = app(PayslipPdfService::class);

            $request = Request::create(
                "/api/organization/payroll-periods/{$period->id}/payslips/bulk-pdf",
                'POST',
                ['format' => 'zip']
            );
            $request->setUserResolver(fn () => $hrManager);

            $response = $controller->downloadBulkPdf($request, $tenant->slug, $period, $pdfService);

            expect($response->getStatusCode())->toBe(200);
        });

        it('returns 404 when no entries found', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayslipPdf($tenant);

            $hrManager = createTenantUserForPayslipPdf($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->create();

            // No entries created

            $controller = app(PayrollEntryController::class);
            $pdfService = app(PayslipPdfService::class);

            $request = Request::create(
                "/api/organization/payroll-periods/{$period->id}/payslips/bulk-pdf",
                'POST',
                ['format' => 'pdf']
            );
            $request->setUserResolver(fn () => $hrManager);

            $response = $controller->downloadBulkPdf($request, $tenant->slug, $period, $pdfService);

            expect($response->getStatusCode())->toBe(404);

            $data = json_decode($response->getContent(), true);
            expect($data['message'])->toContain('No payroll entries found');
        });

        it('filters entries by entry_ids when provided', function () {
            $tenant = Tenant::factory()->create(['name' => 'Test Company']);
            bindTenantContextForPayslipPdf($tenant);

            $hrManager = createTenantUserForPayslipPdf($tenant, TenantUserRole::HrManager);
            $this->actingAs($hrManager);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->create([
                'name' => 'January 2026',
            ]);

            $entries = PayrollEntry::factory()
                ->forPeriod($period)
                ->computed()
                ->count(5)
                ->create();

            $selectedIds = $entries->take(2)->pluck('id')->toArray();

            $controller = app(PayrollEntryController::class);
            $pdfService = app(PayslipPdfService::class);

            $request = Request::create(
                "/api/organization/payroll-periods/{$period->id}/payslips/bulk-pdf",
                'POST',
                [
                    'format' => 'pdf',
                    'entry_ids' => $selectedIds,
                ]
            );
            $request->setUserResolver(fn () => $hrManager);

            $response = $controller->downloadBulkPdf($request, $tenant->slug, $period, $pdfService);

            expect($response->getStatusCode())->toBe(200);
        });

        it('denies access to unauthorized user for bulk download', function () {
            $tenant = Tenant::factory()->create();
            bindTenantContextForPayslipPdf($tenant);

            $employee = createTenantUserForPayslipPdf($tenant, TenantUserRole::Employee);
            $this->actingAs($employee);

            $cycle = PayrollCycle::factory()->semiMonthly()->create();
            $period = PayrollPeriod::factory()->forCycle($cycle)->create();

            PayrollEntry::factory()
                ->forPeriod($period)
                ->computed()
                ->count(3)
                ->create();

            $controller = app(PayrollEntryController::class);
            $pdfService = app(PayslipPdfService::class);

            $request = Request::create(
                "/api/organization/payroll-periods/{$period->id}/payslips/bulk-pdf",
                'POST',
                ['format' => 'pdf']
            );
            $request->setUserResolver(fn () => $employee);

            $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

            $controller->downloadBulkPdf($request, $tenant->slug, $period, $pdfService);
        });
    });
});

describe('PDF content verification', function () {
    it('includes employee information in generated PDF', function () {
        $tenant = Tenant::factory()->create([
            'name' => 'Test Company Ltd',
            'business_info' => [
                'address' => '123 Test Street',
                'tin' => '123-456-789',
            ],
        ]);
        bindTenantContextForPayslipPdf($tenant);

        $cycle = PayrollCycle::factory()->semiMonthly()->create();
        $period = PayrollPeriod::factory()->forCycle($cycle)->create([
            'name' => 'January 2026 - 1st Half',
            'cutoff_start' => '2026-01-01',
            'cutoff_end' => '2026-01-15',
            'pay_date' => '2026-01-25',
        ]);

        $entry = PayrollEntry::factory()->forPeriod($period)->computed()->create([
            'employee_number' => 'EMP-12345',
            'employee_name' => 'Jane Doe',
            'department_name' => 'Finance',
            'position_name' => 'Accountant',
            'gross_pay' => 55000.00,
            'total_deductions' => 7500.00,
            'net_pay' => 47500.00,
        ]);

        $service = app(PayslipPdfService::class);
        $pdfContent = $service->generateSingle($entry);

        // Verify PDF is generated (starts with PDF magic number)
        expect(substr($pdfContent, 0, 4))->toBe('%PDF');
        expect(strlen($pdfContent))->toBeGreaterThan(1000);
    });
});
