<?php

use App\Enums\DocumentRequestStatus;
use App\Enums\DocumentRequestType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\DocumentRequestController;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForDocumentRequest(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserWithRoleForDocumentRequest(Tenant $tenant, TenantUserRole $role): User
{
    $user = User::factory()->create();
    $user->tenants()->attach($tenant->id, [
        'role' => $role->value,
        'invited_at' => now(),
        'invitation_accepted_at' => now(),
    ]);

    return $user;
}

beforeEach(function () {
    config(['app.main_domain' => 'kasamahr.test']);

    \Illuminate\Support\Facades\Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);
});

it('allows employee to create a document request', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocumentRequest($tenant);

    $user = createUserWithRoleForDocumentRequest($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    $controller = new DocumentRequestController;

    $formRequest = \App\Http\Requests\StoreDocumentRequestRequest::create(
        '/api/document-requests',
        'POST',
        [
            'document_type' => DocumentRequestType::Coe->value,
            'notes' => 'Need this for visa application',
        ]
    );
    $formRequest->setUserResolver(fn () => $user);
    $formRequest->setContainer(app());
    $formRequest->validateResolved();

    $response = $controller->store($formRequest);

    $this->assertDatabaseHas('document_requests', [
        'employee_id' => $employee->id,
        'document_type' => DocumentRequestType::Coe->value,
        'status' => DocumentRequestStatus::Pending->value,
        'notes' => 'Need this for visa application',
    ]);
});

it('allows employee to list their own document requests', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocumentRequest($tenant);

    $user = createUserWithRoleForDocumentRequest($tenant, TenantUserRole::Employee);
    $employee = Employee::factory()->create(['user_id' => $user->id]);

    DocumentRequest::factory()->count(3)->forEmployee($employee)->create();

    // Create another employee's requests - should not appear
    $otherEmployee = Employee::factory()->create();
    DocumentRequest::factory()->count(2)->forEmployee($otherEmployee)->create();

    $this->actingAs($user);

    $controller = new DocumentRequestController;
    $request = Request::create('/api/document-requests', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    $data = $response->toResponse($request)->getData(true);
    expect($data['data'])->toHaveCount(3);
});

it('validates required document_type field', function () {
    $validator = \Illuminate\Support\Facades\Validator::make(
        ['notes' => 'Some notes'],
        (new \App\Http\Requests\StoreDocumentRequestRequest)->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('document_type'))->toBeTrue();
});

it('validates document_type must be a valid enum value', function () {
    $validator = \Illuminate\Support\Facades\Validator::make(
        ['document_type' => 'invalid_type'],
        (new \App\Http\Requests\StoreDocumentRequestRequest)->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('document_type'))->toBeTrue();
});

it('returns 404 when user has no employee profile for store', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocumentRequest($tenant);

    $user = createUserWithRoleForDocumentRequest($tenant, TenantUserRole::Employee);

    $this->actingAs($user);

    $controller = new DocumentRequestController;
    $formRequest = \App\Http\Requests\StoreDocumentRequestRequest::create(
        '/api/document-requests',
        'POST',
        ['document_type' => DocumentRequestType::Coe->value]
    );
    $formRequest->setUserResolver(fn () => $user);
    $formRequest->setContainer(app());
    $formRequest->validateResolved();

    $response = $controller->store($formRequest);

    expect($response->getStatusCode())->toBe(404);
});

it('prevents employee from viewing another employees request', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocumentRequest($tenant);

    $user = createUserWithRoleForDocumentRequest($tenant, TenantUserRole::Employee);
    Employee::factory()->create(['user_id' => $user->id]);

    $otherEmployee = Employee::factory()->create();
    $otherRequest = DocumentRequest::factory()->forEmployee($otherEmployee)->create();

    $this->actingAs($user);

    $controller = new DocumentRequestController;
    $request = Request::create("/api/document-requests/{$otherRequest->id}", 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->show($request, $otherRequest);

    expect($response->getStatusCode())->toBe(403);
});
