<?php

namespace App\Http\Controllers\Api;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingSessionRequest;
use App\Http\Requests\UpdateTrainingSessionRequest;
use App\Http\Resources\TrainingSessionListResource;
use App\Http\Resources\TrainingSessionResource;
use App\Models\Course;
use App\Models\TrainingSession;
use App\Services\Training\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TrainingSessionController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    /**
     * Display a listing of training sessions.
     *
     * Supports filtering by status, course, date range.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-view-training');

        $query = TrainingSession::query()
            ->with(['course', 'instructor'])
            ->orderBy('start_date');

        // For employees without manage permission, only show visible sessions
        if (! Gate::allows('can-manage-training')) {
            $query->visibleToEmployees();
        } else {
            if ($request->filled('status')) {
                $status = SessionStatus::tryFrom($request->input('status'));
                if ($status) {
                    $query->byStatus($status);
                }
            }
        }

        if ($request->filled('course_id')) {
            $query->forCourse((int) $request->input('course_id'));
        }

        if ($request->filled('upcoming')) {
            $query->upcoming();
        }

        if ($request->filled('year') && $request->filled('month')) {
            $query->inMonth((int) $request->input('year'), (int) $request->input('month'));
        }

        $sessions = $query->get();

        return TrainingSessionListResource::collection($sessions);
    }

    /**
     * Store a newly created training session.
     */
    public function store(StoreTrainingSessionRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $validated['status'] = $validated['status'] ?? SessionStatus::Draft->value;
        $validated['created_by'] = auth()->user()->employee?->id;

        $session = TrainingSession::create($validated);
        $session->load(['course', 'instructor', 'creator']);

        return (new TrainingSessionResource($session))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified training session.
     */
    public function show(TrainingSession $session): TrainingSessionResource
    {
        // For employees without manage permission, only show visible sessions
        if (! Gate::allows('can-manage-training') && ! $session->status->isVisibleToEmployees()) {
            abort(403, 'This session is not available.');
        }

        Gate::authorize('can-view-training');

        $session->load([
            'course',
            'instructor',
            'creator',
            'enrollments.employee.department',
            'enrollments.employee.position',
            'waitlist.employee.department',
            'waitlist.employee.position',
        ]);

        return new TrainingSessionResource($session);
    }

    /**
     * Update the specified training session.
     */
    public function update(
        UpdateTrainingSessionRequest $request,
        TrainingSession $session
    ): TrainingSessionResource {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $session->update($validated);
        $session->load(['course', 'instructor', 'creator']);

        return new TrainingSessionResource($session);
    }

    /**
     * Remove the specified training session.
     */
    public function destroy(TrainingSession $session): JsonResponse
    {
        Gate::authorize('can-manage-training');

        // Check if session has enrollments
        if ($session->enrollments()->count() > 0) {
            return response()->json([
                'message' => 'Cannot delete a session with enrollments. Cancel the session instead.',
            ], 422);
        }

        $session->delete();

        return response()->json([
            'message' => 'Session deleted successfully.',
        ]);
    }

    /**
     * Publish the session (make it available for enrollment).
     */
    public function publish(TrainingSession $session): TrainingSessionResource
    {
        Gate::authorize('can-manage-training');

        if ($session->status !== SessionStatus::Draft) {
            abort(422, 'Only draft sessions can be published.');
        }

        $session->publish();
        $session->load(['course', 'instructor', 'creator']);

        return new TrainingSessionResource($session);
    }

    /**
     * Cancel the session and notify enrolled employees.
     */
    public function cancel(Request $request, TrainingSession $session): TrainingSessionResource
    {
        Gate::authorize('can-manage-training');

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $session = $this->enrollmentService->cancelSession(
            $session,
            $request->input('reason')
        );

        $session->load(['course', 'instructor', 'creator']);

        return new TrainingSessionResource($session);
    }

    /**
     * Get sessions for a specific course.
     */
    public function forCourse(Course $course): AnonymousResourceCollection
    {
        Gate::authorize('can-view-training');

        $query = $course->sessions()
            ->with(['instructor'])
            ->orderBy('start_date');

        if (! Gate::allows('can-manage-training')) {
            $query->visibleToEmployees();
        }

        return TrainingSessionListResource::collection($query->get());
    }
}
