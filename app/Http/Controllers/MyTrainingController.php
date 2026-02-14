<?php

namespace App\Http\Controllers;

use App\Enums\CourseDeliveryMethod;
use App\Enums\CourseLevel;
use App\Http\Resources\CourseCategoryResource;
use App\Http\Resources\CourseListResource;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class MyTrainingController extends Controller
{
    /**
     * Display the employee training catalog page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-view-training');

        $query = Course::query()
            ->published()
            ->with(['categories', 'prerequisites'])
            ->orderBy('title');

        if ($request->filled('delivery_method')) {
            $method = CourseDeliveryMethod::tryFrom($request->input('delivery_method'));
            if ($method) {
                $query->byDeliveryMethod($method);
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

        $categories = CourseCategory::query()
            ->active()
            ->whereHas('courses', fn ($q) => $q->published())
            ->orderBy('name')
            ->get();

        return Inertia::render('My/Training/Index', [
            'courses' => CourseListResource::collection($courses),
            'categories' => CourseCategoryResource::collection($categories),
            'filters' => [
                'delivery_method' => $request->input('delivery_method'),
                'level' => $request->input('level'),
                'category_id' => $request->input('category_id'),
                'search' => $request->input('search'),
            ],
            'deliveryMethodOptions' => $this->getDeliveryMethodOptions(),
            'levelOptions' => $this->getLevelOptions(),
        ]);
    }

    /**
     * Display the employee course detail page.
     */
    public function show(Course $course): Response
    {
        Gate::authorize('can-view-training');

        if (! $course->isPublished()) {
            abort(404);
        }

        $course->load(['categories', 'prerequisites']);

        return Inertia::render('My/Training/Show', [
            'course' => new CourseResource($course),
        ]);
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
