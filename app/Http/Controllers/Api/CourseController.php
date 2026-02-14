<?php

namespace App\Http\Controllers\Api;

use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Enums\CourseProviderType;
use App\Enums\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Http\Resources\CourseListResource;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    /**
     * Display a listing of courses.
     *
     * Supports filtering by status, delivery_method, provider_type, level, category, and search.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-view-training');

        $query = Course::query()
            ->with(['categories', 'prerequisites'])
            ->orderBy('title');

        // For employees without manage permission, only show published courses
        if (! Gate::allows('can-manage-training')) {
            $query->published();
        } else {
            // Admins can filter by status
            if ($request->filled('status')) {
                $status = CourseStatus::tryFrom($request->input('status'));
                if ($status) {
                    $query->byStatus($status);
                }
            }
        }

        if ($request->filled('delivery_method')) {
            $method = CourseDeliveryMethod::tryFrom($request->input('delivery_method'));
            if ($method) {
                $query->byDeliveryMethod($method);
            }
        }

        if ($request->filled('provider_type')) {
            $type = CourseProviderType::tryFrom($request->input('provider_type'));
            if ($type) {
                $query->byProviderType($type);
            }
        }

        if ($request->filled('level')) {
            $level = CourseLevel::tryFrom($request->input('level'));
            if ($level) {
                $query->byLevel($level);
            }
        }

        if ($request->filled('category_id')) {
            $query->inCategory((int) $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $courses = $query->get();

        return CourseListResource::collection($courses);
    }

    /**
     * Store a newly created course.
     */
    public function store(StoreCourseRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? CourseStatus::Draft->value;
        $validated['created_by'] = auth()->user()->employee?->id;

        $categoryIds = $validated['category_ids'] ?? [];
        $prerequisites = $validated['prerequisites'] ?? [];
        unset($validated['category_ids'], $validated['prerequisites']);

        $course = Course::create($validated);

        // Sync categories
        if (count($categoryIds) > 0) {
            $course->categories()->sync($categoryIds);
        }

        // Sync prerequisites
        if (count($prerequisites) > 0) {
            $prerequisiteData = [];
            foreach ($prerequisites as $prereq) {
                $prerequisiteData[$prereq['id']] = [
                    'is_mandatory' => $prereq['is_mandatory'] ?? true,
                ];
            }
            $course->prerequisites()->sync($prerequisiteData);
        }

        $course->load(['categories', 'prerequisites', 'creator']);

        return (new CourseResource($course))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified course.
     */
    public function show(Course $course): CourseResource
    {
        // For employees without manage permission, only show published courses
        if (! Gate::allows('can-manage-training') && ! $course->isPublished()) {
            abort(403, 'This course is not available.');
        }

        Gate::authorize('can-view-training');

        $course->load(['categories', 'prerequisites', 'requiredBy', 'creator']);

        return new CourseResource($course);
    }

    /**
     * Update the specified course.
     */
    public function update(
        UpdateCourseRequest $request,
        Course $course
    ): CourseResource {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $categoryIds = $validated['category_ids'] ?? null;
        $prerequisites = $validated['prerequisites'] ?? null;
        unset($validated['category_ids'], $validated['prerequisites']);

        $course->update($validated);

        // Sync categories if provided
        if ($categoryIds !== null) {
            $course->categories()->sync($categoryIds);
        }

        // Sync prerequisites if provided
        if ($prerequisites !== null) {
            $prerequisiteData = [];
            foreach ($prerequisites as $prereq) {
                $prerequisiteData[$prereq['id']] = [
                    'is_mandatory' => $prereq['is_mandatory'] ?? true,
                ];
            }
            $course->prerequisites()->sync($prerequisiteData);
        }

        $course->load(['categories', 'prerequisites', 'requiredBy', 'creator']);

        return new CourseResource($course);
    }

    /**
     * Remove the specified course.
     */
    public function destroy(Course $course): JsonResponse
    {
        Gate::authorize('can-manage-training');

        // Check if this course is a prerequisite for other courses
        $requiredByCount = $course->requiredBy()->count();

        if ($requiredByCount > 0) {
            return response()->json([
                'message' => 'Cannot delete this course because it is a prerequisite for other courses. Remove those prerequisite relationships first.',
            ], 422);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ]);
    }

    /**
     * Publish the specified course.
     */
    public function publish(Course $course): CourseResource
    {
        Gate::authorize('can-manage-training');

        if ($course->isPublished()) {
            abort(422, 'This course is already published.');
        }

        $course->publish();
        $course->load(['categories', 'prerequisites', 'requiredBy', 'creator']);

        return new CourseResource($course);
    }

    /**
     * Archive the specified course.
     */
    public function archive(Course $course): CourseResource
    {
        Gate::authorize('can-manage-training');

        if ($course->isArchived()) {
            abort(422, 'This course is already archived.');
        }

        $course->archive();
        $course->load(['categories', 'prerequisites', 'requiredBy', 'creator']);

        return new CourseResource($course);
    }

    /**
     * Duplicate the specified course.
     */
    public function duplicate(Request $request, Course $course): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:courses,code'],
        ]);

        $duplicate = $course->duplicate($request->input('code'));
        $duplicate->created_by = auth()->user()->employee?->id;
        $duplicate->save();

        $duplicate->load(['categories', 'prerequisites', 'creator']);

        return (new CourseResource($duplicate))
            ->response()
            ->setStatusCode(201);
    }
}
