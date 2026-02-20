<?php

use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\KpiTemplateController;
use App\Http\Requests\StoreKpiTemplateRequest;
use App\Http\Requests\UpdateKpiTemplateRequest;
use App\Models\KpiTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindTenantContextForKpiTemplate(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createTenantUserForKpiTemplate(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
{
    $user = User::factory()->create($userAttributes);
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

/**
 * Helper to create a validated store KPI template request.
 */
function createStoreKpiTemplateRequest(array $data, User $user): StoreKpiTemplateRequest
{
    $request = StoreKpiTemplateRequest::create('/api/performance/kpi-templates', 'POST', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());

    $validator = Validator::make($data, (new StoreKpiTemplateRequest)->rules());
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

/**
 * Helper to create a validated update KPI template request.
 */
function createUpdateKpiTemplateRequest(array $data, User $user, int $templateId): UpdateKpiTemplateRequest
{
    $request = UpdateKpiTemplateRequest::create("/api/performance/kpi-templates/{$templateId}", 'PUT', $data);
    $request->setUserResolver(fn () => $user);
    $request->setContainer(app());
    $request->setRouteResolver(fn () => new class($templateId)
    {
        private int $id;

        public function __construct(int $id)
        {
            $this->id = $id;
        }

        public function parameter($name)
        {
            return $this->id;
        }
    });

    // Override the unique rule for testing
    $rules = (new UpdateKpiTemplateRequest)->rules();
    $rules['code'] = ['required', 'string', 'max:50'];

    $validator = Validator::make($data, $rules);
    $validator->validate();

    $reflection = new ReflectionClass($request);
    $property = $reflection->getProperty('validator');
    $property->setAccessible(true);
    $property->setValue($request, $validator);

    return $request;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

describe('KpiTemplate API', function () {
    it('returns filtered KPI templates list on index', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $salesTemplate = KpiTemplate::factory()->salesCategory()->create([
            'name' => 'Sales Target',
            'code' => 'SALES-001',
            'is_active' => true,
            'category' => 'Sales',
        ]);

        KpiTemplate::factory()->qualityCategory()->create([
            'name' => 'Quality Score',
            'code' => 'QUAL-001',
            'is_active' => true,
            'category' => 'Quality',
        ]);

        KpiTemplate::factory()->inactive()->create([
            'name' => 'Inactive KPI',
            'code' => 'INA-001',
            'category' => 'Other',
        ]);

        $controller = new KpiTemplateController;

        // Test without filters - returns all
        $request = Request::create('/api/performance/kpi-templates', 'GET');
        $response = $controller->index($request);
        expect($response->count())->toBe(3);

        // Test filter by active status
        $activeRequest = Request::create('/api/performance/kpi-templates', 'GET', ['is_active' => '1']);
        $activeResponse = $controller->index($activeRequest);
        expect($activeResponse->count())->toBe(2);

        // Test filter by category
        $salesRequest = Request::create('/api/performance/kpi-templates', 'GET', ['category' => 'Sales']);
        $salesResponse = $controller->index($salesRequest);
        expect($salesResponse->count())->toBe(1);
        expect($salesResponse->first()->name)->toBe('Sales Target');
    });

    it('creates a KPI template', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new KpiTemplateController;

        $templateData = [
            'name' => 'Monthly Sales Target',
            'code' => 'MST-001',
            'description' => 'Monthly sales performance indicator',
            'metric_unit' => 'PHP',
            'default_target' => 100000,
            'default_weight' => 1.5,
            'category' => 'Sales',
            'is_active' => true,
        ];

        $storeRequest = createStoreKpiTemplateRequest($templateData, $admin);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['name'])->toBe('Monthly Sales Target');
        expect($data['code'])->toBe('MST-001');
        expect($data['metric_unit'])->toBe('PHP');
        expect((float) $data['default_target'])->toBe(100000.0);
        expect((float) $data['default_weight'])->toBe(1.5);

        $this->assertDatabaseHas('kpi_templates', [
            'name' => 'Monthly Sales Target',
            'code' => 'MST-001',
        ]);
    });

    it('creates a percentage-based KPI template', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new KpiTemplateController;

        $templateData = [
            'name' => 'Customer Satisfaction',
            'code' => 'CSAT-001',
            'metric_unit' => '%',
            'default_target' => 95,
            'default_weight' => 1.0,
            'category' => 'Quality',
            'is_active' => true,
        ];

        $storeRequest = createStoreKpiTemplateRequest($templateData, $admin);
        $response = $controller->store($storeRequest);

        expect($response->getStatusCode())->toBe(201);

        $data = json_decode($response->getContent(), true);
        expect($data['metric_unit'])->toBe('%');
        expect((float) $data['default_target'])->toBe(95.0);
    });

    it('validates required fields', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $rules = (new StoreKpiTemplateRequest)->rules();
        $validator = Validator::make([], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('name'))->toBeTrue();
        expect($validator->errors()->has('code'))->toBeTrue();
        expect($validator->errors()->has('metric_unit'))->toBeTrue();
    });

    it('validates unique code constraint', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        KpiTemplate::factory()->create(['code' => 'DUP-001']);

        $rules = (new StoreKpiTemplateRequest)->rules();
        $validator = Validator::make([
            'name' => 'Duplicate Code Template',
            'code' => 'DUP-001',
            'metric_unit' => 'units',
        ], $rules);

        expect($validator->fails())->toBeTrue();
        expect($validator->errors()->has('code'))->toBeTrue();
    });

    it('returns template with assignment count on show', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $template = KpiTemplate::factory()->create([
            'name' => 'Sales Target',
            'code' => 'SALES-001',
            'metric_unit' => 'PHP',
        ]);

        $controller = new KpiTemplateController;
        $response = $controller->show($template);
        $data = $response->toArray(request());

        expect($data['name'])->toBe('Sales Target');
        expect($data['code'])->toBe('SALES-001');
        expect($data['metric_unit'])->toBe('PHP');
    });

    it('updates KPI template', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new KpiTemplateController;

        $template = KpiTemplate::factory()->create([
            'name' => 'Original Name',
            'code' => 'ORI-001',
            'metric_unit' => 'units',
            'is_active' => true,
        ]);

        $updateData = [
            'name' => 'Updated Name',
            'code' => 'ORI-001',
            'metric_unit' => 'PHP',
            'default_target' => 50000,
            'default_weight' => 2.0,
            'description' => 'Updated description',
            'is_active' => false,
        ];

        $updateRequest = createUpdateKpiTemplateRequest($updateData, $admin, $template->id);
        $response = $controller->update($updateRequest, $template);

        $data = $response->toArray(request());
        expect($data['name'])->toBe('Updated Name');
        expect($data['metric_unit'])->toBe('PHP');
        expect($data['is_active'])->toBeFalse();

        $this->assertDatabaseHas('kpi_templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
            'metric_unit' => 'PHP',
        ]);
    });

    it('soft deletes KPI template', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        $controller = new KpiTemplateController;

        $template = KpiTemplate::factory()->create([
            'name' => 'To Be Deleted',
            'code' => 'DEL-001',
        ]);

        $response = $controller->destroy($template);

        expect($response->getStatusCode())->toBe(200);

        // Verify soft delete
        expect(KpiTemplate::find($template->id))->toBeNull();
        expect(KpiTemplate::withTrashed()->find($template->id))->not->toBeNull();
    });

    it('filters templates by search term', function () {
        $tenant = Tenant::factory()->create();
        bindTenantContextForKpiTemplate($tenant);

        $admin = createTenantUserForKpiTemplate($tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        KpiTemplate::factory()->create([
            'name' => 'Monthly Sales Target',
            'code' => 'MST-001',
            'category' => 'Sales',
        ]);

        KpiTemplate::factory()->create([
            'name' => 'Customer Satisfaction',
            'code' => 'CSAT-001',
            'category' => 'Quality',
        ]);

        $controller = new KpiTemplateController;

        $searchRequest = Request::create('/api/performance/kpi-templates', 'GET', ['search' => 'Monthly']);
        $searchResponse = $controller->index($searchRequest);
        expect($searchResponse->count())->toBe(1);
        expect($searchResponse->first()->name)->toBe('Monthly Sales Target');
    });
});
