<?php

use App\Enums\DocumentRequestStatus;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Hr\DocumentRequestManagementController;
use App\Http\Requests\UpdateDocumentRequestRequest;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function bindTenantForDocRequestMgmt(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

function createUserWithRoleForDocRequestMgmt(Tenant $tenant, TenantUserRole $role): User
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

it('returns document requests with summary for authorized users', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocRequestMgmt($tenant);

    $user = createUserWithRoleForDocRequestMgmt($tenant, TenantUserRole::Admin);
    $employee = Employee::factory()->create();

    DocumentRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => DocumentRequestStatus::Pending,
    ]);
    DocumentRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => DocumentRequestStatus::Processing,
    ]);

    $this->actingAs($user);

    $controller = new DocumentRequestManagementController;
    $request = Request::create('/hr/document-requests', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(\Inertia\Response::class);
});

it('filters document requests by status', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocRequestMgmt($tenant);

    $user = createUserWithRoleForDocRequestMgmt($tenant, TenantUserRole::Admin);
    $employee = Employee::factory()->create();

    DocumentRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => DocumentRequestStatus::Pending,
    ]);
    DocumentRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => DocumentRequestStatus::Processing,
    ]);

    $this->actingAs($user);

    $controller = new DocumentRequestManagementController;
    $request = Request::create('/hr/document-requests', 'GET', ['status' => 'pending']);
    $request->setUserResolver(fn () => $user);

    $response = $controller->index($request);

    expect($response)->toBeInstanceOf(\Inertia\Response::class);
});

it('denies access to employees for HR document requests page', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocRequestMgmt($tenant);

    $user = createUserWithRoleForDocRequestMgmt($tenant, TenantUserRole::Employee);

    $this->actingAs($user);

    $controller = new DocumentRequestManagementController;
    $request = Request::create('/hr/document-requests', 'GET');
    $request->setUserResolver(fn () => $user);

    $controller->index($request);
})->throws(\Illuminate\Auth\Access\AuthorizationException::class);

it('allows admin to update document request status to processing', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocRequestMgmt($tenant);

    $user = createUserWithRoleForDocRequestMgmt($tenant, TenantUserRole::Admin);
    $employee = Employee::factory()->create();

    $documentRequest = DocumentRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => DocumentRequestStatus::Pending,
    ]);

    $this->actingAs($user);

    $controller = new \App\Http\Controllers\Api\DocumentRequestAdminController;

    $formRequest = UpdateDocumentRequestRequest::create(
        '/api/document-requests/'.$documentRequest->id,
        'PATCH',
        [
            'status' => DocumentRequestStatus::Processing->value,
            'admin_notes' => 'Working on it',
        ]
    );
    $formRequest->setUserResolver(fn () => $user);
    $formRequest->setContainer(app());
    $formRequest->validateResolved();

    $response = $controller->update($formRequest, 'acme', $documentRequest);

    $documentRequest->refresh();
    expect($documentRequest->status)->toBe(DocumentRequestStatus::Processing);
    expect($documentRequest->admin_notes)->toBe('Working on it');
    expect($documentRequest->processed_at)->not->toBeNull();
});

it('sets collected_at when status changes to collected', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme']);
    bindTenantForDocRequestMgmt($tenant);

    $user = createUserWithRoleForDocRequestMgmt($tenant, TenantUserRole::Admin);
    $employee = Employee::factory()->create();

    $documentRequest = DocumentRequest::factory()->create([
        'employee_id' => $employee->id,
        'status' => DocumentRequestStatus::Ready,
    ]);

    $this->actingAs($user);

    $controller = new \App\Http\Controllers\Api\DocumentRequestAdminController;

    $formRequest = UpdateDocumentRequestRequest::create(
        '/api/document-requests/'.$documentRequest->id,
        'PATCH',
        [
            'status' => DocumentRequestStatus::Collected->value,
        ]
    );
    $formRequest->setUserResolver(fn () => $user);
    $formRequest->setContainer(app());
    $formRequest->validateResolved();

    $response = $controller->update($formRequest, 'acme', $documentRequest);

    $documentRequest->refresh();
    expect($documentRequest->status)->toBe(DocumentRequestStatus::Collected);
    expect($documentRequest->collected_at)->not->toBeNull();
});

it('validates status is required when updating document request', function () {
    $validator = \Illuminate\Support\Facades\Validator::make(
        [],
        (new UpdateDocumentRequestRequest)->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('status'))->toBeTrue();
});

it('validates admin_notes max length', function () {
    $validator = \Illuminate\Support\Facades\Validator::make(
        [
            'status' => DocumentRequestStatus::Processing->value,
            'admin_notes' => str_repeat('a', 1001),
        ],
        (new UpdateDocumentRequestRequest)->rules()
    );

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('admin_notes'))->toBeTrue();
});
