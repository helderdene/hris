<?php

namespace App\Http\Controllers;

use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Enums\CourseProviderType;
use App\Enums\CourseStatus;
use App\Http\Resources\CourseCategoryResource;
use App\Http\Resources\CourseListResource;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TrainingController extends Controller
{
    /**
     * Display the admin courses index page.
     */
    public function coursesIndex(Request $request): Response
    {
        Gate::authorize('can-manage-training');

        $query = Course::query()
            ->with(['categories', 'prerequisites'])
            ->orderBy('title');

        if ($request->filled('status')) {
            $status = CourseStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->byStatus($status);
            }
        }

        if ($request->filled('delivery_method')) {
            $method = CourseDeliveryMethod::tryFrom($request->input('delivery_method'));
            if ($method) {
                $query->byDeliveryMethod($method);
            }
        }

        if ($request->filled('category_id')) {
            $query->inCategory((int) $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        $courses = $query->get();

        $categories = CourseCategory::query()
            ->active()
            ->orderBy('name')
            ->get();

        return Inertia::render('Training/Courses/Index', [
            'courses' => CourseListResource::collection($courses),
            'categories' => CourseCategoryResource::collection($categories),
            'filters' => [
                'status' => $request->input('status'),
                'delivery_method' => $request->input('delivery_method'),
                'category_id' => $request->input('category_id'),
                'search' => $request->input('search'),
            ],
            'statusOptions' => $this->getStatusOptions(),
            'deliveryMethodOptions' => $this->getDeliveryMethodOptions(),
            'providerTypeOptions' => $this->getProviderTypeOptions(),
            'levelOptions' => $this->getLevelOptions(),
        ]);
    }

    /**
     * Display the admin course detail page.
     */
    public function coursesShow(Course $course): Response
    {
        Gate::authorize('can-manage-training');

        $course->load(['categories', 'prerequisites', 'requiredBy', 'creator']);

        $categories = CourseCategory::query()
            ->active()
            ->orderBy('name')
            ->get();

        $availablePrerequisites = Course::query()
            ->where('id', '!=', $course->id)
            ->orderBy('title')
            ->get(['id', 'title', 'code']);

        return Inertia::render('Training/Courses/Show', [
            'course' => new CourseResource($course),
            'categories' => CourseCategoryResource::collection($categories),
            'availablePrerequisites' => $availablePrerequisites,
            'statusOptions' => $this->getStatusOptions(),
            'deliveryMethodOptions' => $this->getDeliveryMethodOptions(),
            'providerTypeOptions' => $this->getProviderTypeOptions(),
            'levelOptions' => $this->getLevelOptions(),
        ]);
    }

    /**
     * Display the admin categories index page.
     */
    public function categoriesIndex(): Response
    {
        Gate::authorize('can-manage-training');

        $categories = CourseCategory::query()
            ->with(['parent', 'children'])
            ->withCount('courses')
            ->orderBy('name')
            ->get();

        return Inertia::render('Training/Categories/Index', [
            'categories' => CourseCategoryResource::collection($categories),
        ]);
    }

    /**
     * Get status options for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getStatusOptions(): array
    {
        return array_map(
            fn (CourseStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            CourseStatus::cases()
        );
    }

    /**
     * Get delivery method options for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getDeliveryMethodOptions(): array
    {
        return array_map(
            fn (CourseDeliveryMethod $method) => [
                'value' => $method->value,
                'label' => $method->label(),
            ],
            CourseDeliveryMethod::cases()
        );
    }

    /**
     * Get provider type options for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getProviderTypeOptions(): array
    {
        return array_map(
            fn (CourseProviderType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            CourseProviderType::cases()
        );
    }

    /**
     * Get level options for frontend.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getLevelOptions(): array
    {
        return array_map(
            fn (CourseLevel $level) => [
                'value' => $level->value,
                'label' => $level->label(),
            ],
            CourseLevel::cases()
        );
    }
}
