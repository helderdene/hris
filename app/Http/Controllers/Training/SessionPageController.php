<?php

namespace App\Http\Controllers\Training;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseListResource;
use App\Http\Resources\TrainingSessionListResource;
use App\Http\Resources\TrainingSessionResource;
use App\Models\Course;
use App\Models\Employee;
use App\Models\TrainingSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SessionPageController extends Controller
{
    /**
     * Display the training sessions index page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-training');

        $query = TrainingSession::query()
            ->with(['course', 'instructor'])
            ->orderBy('start_date', 'desc');

        if ($request->filled('status')) {
            $status = SessionStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->byStatus($status);
            }
        }

        if ($request->filled('course_id')) {
            $query->forCourse((int) $request->input('course_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($q) => $q->where('title', 'like', "%{$search}%"));
            });
        }

        $sessions = $query->get();

        $courses = Course::query()
            ->published()
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'max_participants']);

        $instructors = Employee::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        return Inertia::render('Training/Sessions/Index', [
            'sessions' => TrainingSessionListResource::collection($sessions),
            'courses' => CourseListResource::collection($courses),
            'instructors' => $instructors->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
            ]),
            'filters' => [
                'status' => $request->input('status'),
                'course_id' => $request->input('course_id'),
                'search' => $request->input('search'),
            ],
            'statusOptions' => $this->getStatusOptions(),
        ]);
    }

    /**
     * Display the training session detail page.
     */
    public function show(TrainingSession $session): Response
    {
        Gate::authorize('can-manage-training');

        $session->load([
            'course',
            'instructor',
            'creator',
            'enrollments.employee.department',
            'enrollments.employee.position',
            'waitlist.employee.department',
            'waitlist.employee.position',
        ]);

        $courses = Course::query()
            ->published()
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'max_participants']);

        $instructors = Employee::query()
            ->active()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name']);

        // Employees available for enrollment (not already enrolled or on waitlist)
        $enrolledIds = $session->enrollments()->pluck('employee_id');
        $waitlistIds = $session->waitlist()->waiting()->pluck('employee_id');
        $excludeIds = $enrolledIds->merge($waitlistIds);

        $availableEmployees = Employee::query()
            ->active()
            ->whereNotIn('id', $excludeIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'employee_number']);

        return Inertia::render('Training/Sessions/Show', [
            'session' => new TrainingSessionResource($session),
            'courses' => CourseListResource::collection($courses),
            'instructors' => $instructors->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
            ]),
            'availableEmployees' => $availableEmployees->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
                'employee_number' => $e->employee_number,
            ]),
            'statusOptions' => $this->getStatusOptions(),
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
            fn (SessionStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->badgeColor(),
            ],
            SessionStatus::cases()
        );
    }
}
