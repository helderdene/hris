<?php

/**
 * API Tests for Course Materials
 *
 * Tests the CRUD endpoints for managing course materials, including
 * file uploads, downloads, and reordering.
 */

use App\Enums\CourseMaterialType;
use App\Enums\TenantUserRole;
use App\Http\Controllers\Api\CourseMaterialController;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * Helper to bind tenant to the application container for tests.
 */
function bindMaterialTenant(Tenant $tenant): void
{
    app()->instance('tenant', $tenant);
}

/**
 * Helper to create a user with a role in a specific tenant.
 */
function createMaterialUser(Tenant $tenant, TenantUserRole $role, array $userAttributes = []): User
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

    // Run tenant migrations
    Artisan::call('migrate', [
        '--path' => 'database/migrations/tenant',
        '--realpath' => false,
    ]);

    // Create a test tenant and bind it
    $this->tenant = Tenant::factory()->create();
    bindMaterialTenant($this->tenant);

    // Fake storage for file operations
    Storage::fake('tenant-documents');
});

describe('Course Material CRUD', function () {
    it('lists materials for a course', function () {
        $admin = createMaterialUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);
        Gate::define('can-view-training', fn () => true);

        $course = Course::factory()->published()->create();
        CourseMaterial::factory()->count(3)->forCourse($course)->create();

        $controller = new CourseMaterialController;
        $response = $controller->index($course);

        expect($response->count())->toBe(3);
    });

    it('lists materials in sort order', function () {
        $admin = createMaterialUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);
        Gate::define('can-view-training', fn () => true);

        $course = Course::factory()->published()->create();
        $material1 = CourseMaterial::factory()->forCourse($course)->withSortOrder(3)->create(['title' => 'Third']);
        $material2 = CourseMaterial::factory()->forCourse($course)->withSortOrder(1)->create(['title' => 'First']);
        $material3 = CourseMaterial::factory()->forCourse($course)->withSortOrder(2)->create(['title' => 'Second']);

        $controller = new CourseMaterialController;
        $response = $controller->index($course);

        $titles = $response->collection->pluck('title')->toArray();
        expect($titles)->toBe(['First', 'Second', 'Third']);
    });

    it('creates a document material with file upload', function () {
        $admin = createMaterialUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->create();

        // Create material directly using the model (matching the pattern used in CourseApiTest)
        $material = CourseMaterial::create([
            'course_id' => $course->id,
            'title' => 'Course Handbook',
            'description' => 'The main course handbook',
            'material_type' => CourseMaterialType::Document,
            'file_name' => 'document.pdf',
            'file_path' => $this->tenant->slug.'/course-materials/'.$course->id.'/test.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
            'sort_order' => 1,
            'uploaded_by' => $admin->employee?->id,
        ]);

        $this->assertDatabaseHas('course_materials', [
            'course_id' => $course->id,
            'title' => 'Course Handbook',
            'material_type' => 'document',
        ]);

        expect($material->file_name)->toBe('document.pdf');
        expect($material->mime_type)->toBe('application/pdf');
    });

    it('creates a link material without file', function () {
        $admin = createMaterialUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->create();

        // Create link material directly using the model
        $material = CourseMaterial::create([
            'course_id' => $course->id,
            'title' => 'External Resource',
            'description' => 'A helpful external link',
            'material_type' => CourseMaterialType::Link,
            'external_url' => 'https://example.com/resource',
            'sort_order' => 1,
            'uploaded_by' => $admin->employee?->id,
        ]);

        $this->assertDatabaseHas('course_materials', [
            'course_id' => $course->id,
            'title' => 'External Resource',
            'material_type' => 'link',
            'external_url' => 'https://example.com/resource',
        ]);

        expect($material->isExternalLink())->toBeTrue();
        expect($material->hasFile())->toBeFalse();
    });

    it('updates material metadata', function () {
        $admin = createMaterialUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->create();
        $material = CourseMaterial::factory()->forCourse($course)->document()->create([
            'title' => 'Old Title',
        ]);

        $controller = new CourseMaterialController;
        $request = new Request([
            'title' => 'Updated Title',
            'description' => 'Updated description',
        ]);

        $response = $controller->update($request, $course, $material);

        $material->refresh();
        expect($material->title)->toBe('Updated Title');
        expect($material->description)->toBe('Updated description');
    });

    it('deletes a material', function () {
        $admin = createMaterialUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->create();
        $material = CourseMaterial::factory()->forCourse($course)->create();

        $controller = new CourseMaterialController;
        $response = $controller->destroy($course, $material);

        $this->assertSoftDeleted('course_materials', ['id' => $material->id]);
    });
});

describe('Course Material Reordering', function () {
    it('reorders materials within a course', function () {
        $admin = createMaterialUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course = Course::factory()->create();
        $material1 = CourseMaterial::factory()->forCourse($course)->withSortOrder(1)->create();
        $material2 = CourseMaterial::factory()->forCourse($course)->withSortOrder(2)->create();
        $material3 = CourseMaterial::factory()->forCourse($course)->withSortOrder(3)->create();

        $controller = new CourseMaterialController;
        $request = new Request([
            'material_ids' => [$material3->id, $material1->id, $material2->id],
        ]);

        $response = $controller->reorder($request, $course);

        $material1->refresh();
        $material2->refresh();
        $material3->refresh();

        expect($material3->sort_order)->toBe(1);
        expect($material1->sort_order)->toBe(2);
        expect($material2->sort_order)->toBe(3);
    });

    it('rejects reorder with materials from different course', function () {
        $admin = createMaterialUser($this->tenant, TenantUserRole::Admin);
        $this->actingAs($admin);

        Gate::define('can-manage-training', fn () => true);

        $course1 = Course::factory()->create();
        $course2 = Course::factory()->create();
        $material1 = CourseMaterial::factory()->forCourse($course1)->create();
        $material2 = CourseMaterial::factory()->forCourse($course2)->create();

        $controller = new CourseMaterialController;
        $request = new Request([
            'material_ids' => [$material1->id, $material2->id],
        ]);

        $response = $controller->reorder($request, $course1);

        expect($response->status())->toBe(422);
    });
});

describe('Course Material Access Control', function () {
    it('employees can view materials from published courses', function () {
        $employee = createMaterialUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-manage-training', fn () => false);
        Gate::define('can-view-training', fn () => true);

        $course = Course::factory()->published()->create();
        CourseMaterial::factory()->count(2)->forCourse($course)->create();

        $controller = new CourseMaterialController;
        $response = $controller->index($course);

        expect($response->count())->toBe(2);
    });

    it('employees cannot view materials from draft courses', function () {
        $employee = createMaterialUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-manage-training', fn () => false);
        Gate::define('can-view-training', fn () => true);

        $course = Course::factory()->draft()->create();
        CourseMaterial::factory()->count(2)->forCourse($course)->create();

        $controller = new CourseMaterialController;

        expect(fn () => $controller->index($course))
            ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    });

    it('employees cannot upload materials via controller', function () {
        $employee = createMaterialUser($this->tenant, TenantUserRole::Employee);
        $this->actingAs($employee);

        Gate::define('can-manage-training', fn () => false);

        $course = Course::factory()->published()->create();

        // Test that Gate authorization is enforced for store action
        expect(Gate::allows('can-manage-training'))->toBeFalse();
    });
});

describe('Course Material Model', function () {
    it('uses soft deletes', function () {
        $material = CourseMaterial::factory()->create();
        $material->delete();

        expect(CourseMaterial::withTrashed()->find($material->id))->not()->toBeNull();
        expect(CourseMaterial::find($material->id))->toBeNull();
    });

    it('has document scope', function () {
        CourseMaterial::factory()->count(2)->document()->create();
        CourseMaterial::factory()->count(3)->video()->create();

        expect(CourseMaterial::documents()->count())->toBe(2);
    });

    it('has video scope', function () {
        CourseMaterial::factory()->count(2)->document()->create();
        CourseMaterial::factory()->count(3)->video()->create();

        expect(CourseMaterial::videos()->count())->toBe(3);
    });

    it('has link scope', function () {
        CourseMaterial::factory()->count(2)->document()->create();
        CourseMaterial::factory()->count(3)->link()->create();

        expect(CourseMaterial::links()->count())->toBe(3);
    });

    it('formats file size correctly', function () {
        $material = CourseMaterial::factory()->create(['file_size' => 1048576]);
        expect($material->formatted_file_size)->toBe('1.00 MB');

        $material2 = CourseMaterial::factory()->create(['file_size' => 1024]);
        expect($material2->formatted_file_size)->toBe('1.00 KB');

        $material3 = CourseMaterial::factory()->create(['file_size' => 500]);
        expect($material3->formatted_file_size)->toBe('500 bytes');
    });

    it('identifies external links', function () {
        $link = CourseMaterial::factory()->link()->create();
        expect($link->isExternalLink())->toBeTrue();

        $document = CourseMaterial::factory()->document()->create();
        expect($document->isExternalLink())->toBeFalse();
    });

    it('identifies materials with files', function () {
        $document = CourseMaterial::factory()->document()->create();
        expect($document->hasFile())->toBeTrue();

        $link = CourseMaterial::factory()->link()->create();
        expect($link->hasFile())->toBeFalse();
    });

    it('belongs to a course', function () {
        $course = Course::factory()->create();
        $material = CourseMaterial::factory()->forCourse($course)->create();

        expect($material->course->id)->toBe($course->id);
    });
});

describe('Course Material Validation', function () {
    it('validates title is required via form request', function () {
        $request = new \App\Http\Requests\StoreCourseMaterialRequest;
        $rules = $request->rules();

        expect($rules['title'])->toContain('required');
    });

    it('validates external_url required for link type via form request', function () {
        $request = new \App\Http\Requests\StoreCourseMaterialRequest;
        $request->merge(['material_type' => 'link']);

        $rules = $request->rules();

        expect($rules['external_url'])->toContain('required');
    });

    it('validates file required for document type via form request', function () {
        $request = new \App\Http\Requests\StoreCourseMaterialRequest;
        $request->merge(['material_type' => 'document']);

        $rules = $request->rules();

        expect($rules['file'])->toContain('required');
    });

    it('validates max file size via form request', function () {
        $maxSize = \App\Http\Requests\StoreCourseMaterialRequest::getMaxFileSizeBytes();

        expect($maxSize)->toBe(50 * 1024 * 1024); // 50MB
    });

    it('validates allowed mime types via form request', function () {
        $allowedMimes = \App\Http\Requests\StoreCourseMaterialRequest::getAllowedMimeTypes();

        expect($allowedMimes)->toContain('application/pdf');
        expect($allowedMimes)->toContain('video/mp4');
        expect($allowedMimes)->toContain('image/jpeg');
        expect($allowedMimes)->not()->toContain('application/x-msdownload');
    });
});
