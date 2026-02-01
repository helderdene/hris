<?php

use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SwitchTenantDatabase;
use App\Models\Tenant;
use App\Services\Tenant\TenantDatabaseManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

it('extracts subdomain from request host and resolves tenant', function () {
    $tenant = Tenant::factory()->create(['slug' => 'acme-corp']);

    $request = Request::create('http://acme-corp.kasamahr.test/dashboard');

    $middleware = new ResolveTenant;

    $response = $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    expect(app()->bound('tenant'))->toBeTrue();
    expect(app('tenant'))->toBeInstanceOf(Tenant::class);
    expect(app('tenant')->slug)->toBe('acme-corp');
});

it('looks up tenant by slug from subdomain', function () {
    $tenant = Tenant::factory()->create(['slug' => 'test-company', 'name' => 'Test Company Inc']);

    $request = Request::create('http://test-company.kasamahr.test/dashboard');

    $middleware = new ResolveTenant;

    $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    expect(tenant())->not->toBeNull();
    expect(tenant()->name)->toBe('Test Company Inc');
});

it('returns 404 for unrecognized subdomains', function () {
    $request = Request::create('http://unknown-tenant.kasamahr.test/dashboard');

    $middleware = new ResolveTenant;

    $middleware->handle($request, function ($req) {
        return new Response('OK');
    });
})->throws(NotFoundHttpException::class, 'Tenant not found');

it('bypasses tenant resolution for main domain', function () {
    // Clear any previously bound tenant
    app()->forgetInstance('tenant');

    $request = Request::create('http://kasamahr.test/');

    $middleware = new ResolveTenant;

    $response = $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    expect($response->getStatusCode())->toBe(200);
    expect(tenant())->toBeNull();
});

it('binds resolved tenant to app container as singleton', function () {
    $tenant = Tenant::factory()->create(['slug' => 'singleton-test']);

    $request = Request::create('http://singleton-test.kasamahr.test/dashboard');

    $middleware = new ResolveTenant;

    $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    // Verify tenant is bound as singleton (same instance on multiple calls)
    $firstCall = app('tenant');
    $secondCall = app('tenant');

    expect($firstCall)->toBe($secondCall);
    expect($firstCall->id)->toBe($tenant->id);
});

it('switches database connection after tenant resolution', function () {
    $tenant = Tenant::factory()->create(['slug' => 'db-switch-test']);
    $manager = app(TenantDatabaseManager::class);

    // Create the tenant schema first
    $manager->createSchema($tenant);

    // Bind the tenant to container (simulating ResolveTenant middleware)
    app()->instance('tenant', $tenant);

    $request = Request::create('http://db-switch-test.kasamahr.test/dashboard');

    $middleware = new SwitchTenantDatabase($manager);

    $middleware->handle($request, function ($req) {
        return new Response('OK');
    });

    // Verify the tenant connection was switched
    if (config('database.default') === 'sqlite') {
        $expectedPath = database_path('tenant_db-switch-test.sqlite');
        expect(config('database.connections.tenant.database'))->toBe($expectedPath);

        // Clean up
        @unlink($expectedPath);
    } else {
        expect(config('database.connections.tenant.database'))->toBe('kasamahr_tenant_db-switch-test');
    }
});
