<?php

namespace App\Http\Controllers\My;

use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TrainingEnrollmentResource;
use App\Http\Resources\TrainingSessionListResource;
use App\Http\Resources\TrainingSessionResource;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Services\Training\EnrollmentService;
use App\Services\Training\ICalExportService;
use App\Services\Training\TrainingEnrollmentRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MyTrainingSessionsController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService,
        protected ICalExportService $icalService,
        protected TrainingEnrollmentRequestService $enrollmentRequestService
    ) {}

    /**
     * Display available training sessions for enrollment.
     */
    public function index(Request $request): InertiaResponse
    {
        Gate::authorize('can-view-training');

        $employee = auth()->user()->employee;

        $query = TrainingSession::query()
            ->with(['course'])
            ->scheduled()
            ->upcoming()
            ->orderBy('start_date');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhereHas('course', fn ($q) => $q->where('title', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('course_id')) {
            $query->forCourse((int) $request->input('course_id'));
        }

        $sessions = $query->get();

        // Get employee's current enrollments and waitlists for status display
        $enrolledSessionIds = $employee?->activeTrainingEnrollments()->pluck('training_session_id') ?? collect();
        $waitlistedSessionIds = $employee?->activeTrainingWaitlists()->pluck('training_session_id') ?? collect();

        return Inertia::render('My/Training/Sessions/Index', [
            'sessions' => TrainingSessionListResource::collection($sessions),
            'enrolledSessionIds' => $enrolledSessionIds,
            'waitlistedSessionIds' => $waitlistedSessionIds,
            'filters' => [
                'search' => $request->input('search'),
                'course_id' => $request->input('course_id'),
            ],
        ]);
    }

    /**
     * Display session details with enrollment options.
     */
    public function show(TrainingSession $session): InertiaResponse
    {
        Gate::authorize('can-view-training');

        if (! $session->status->isVisibleToEmployees()) {
            abort(404);
        }

        $session->load(['course', 'instructor']);

        $employee = auth()->user()->employee;

        // Check enrollment status
        $enrollment = $employee ? $session->enrollments()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                EnrollmentStatus::Pending->value,
                EnrollmentStatus::Confirmed->value,
            ])
            ->first() : null;

        $waitlistEntry = $employee ? $session->waitlist()
            ->where('employee_id', $employee->id)
            ->waiting()
            ->first() : null;

        $isPending = $enrollment !== null && $enrollment->status === EnrollmentStatus::Pending;
        $isEnrolled = $enrollment !== null && $enrollment->status === EnrollmentStatus::Confirmed;
        $hasNoEnrollment = $enrollment === null && $waitlistEntry === null;

        return Inertia::render('My/Training/Sessions/Show', [
            'session' => new TrainingSessionResource($session),
            'enrollment' => $enrollment ? new TrainingEnrollmentResource($enrollment) : null,
            'waitlistPosition' => $waitlistEntry?->position,
            'isPending' => $isPending,
            'isEnrolled' => $isEnrolled,
            'isOnWaitlist' => $waitlistEntry !== null,
            'canEnroll' => $session->status->isEnrollable() && $hasNoEnrollment,
            'canRequestEnrollment' => $session->status->isEnrollable() && $hasNoEnrollment,
        ]);
    }

    /**
     * Enroll the current employee in a session.
     */
    public function enroll(TrainingSession $session): JsonResponse
    {
        Gate::authorize('can-view-training');

        $employee = auth()->user()->employee;

        if (! $employee) {
            abort(403, 'Only employees can enroll in training sessions.');
        }

        $result = $this->enrollmentService->enroll($session, $employee);

        if ($result instanceof TrainingEnrollment) {
            return response()->json([
                'message' => 'You have been enrolled in this session.',
                'enrolled' => true,
            ]);
        }

        return response()->json([
            'message' => 'Session is full. You have been added to the waitlist.',
            'enrolled' => false,
            'waitlist_position' => $result->position,
        ]);
    }

    /**
     * Cancel the current employee's enrollment.
     */
    public function cancelEnrollment(TrainingEnrollment $enrollment): JsonResponse
    {
        Gate::authorize('can-view-training');

        $employee = auth()->user()->employee;

        if ($enrollment->employee_id !== $employee?->id) {
            abort(403);
        }

        $this->enrollmentService->cancelEnrollment($enrollment, $employee, 'Cancelled by employee');

        return response()->json([
            'message' => 'Your enrollment has been cancelled.',
        ]);
    }

    /**
     * Submit an enrollment request for approval.
     */
    public function requestEnrollment(Request $request, TrainingSession $session): JsonResponse
    {
        Gate::authorize('can-view-training');

        $employee = auth()->user()->employee;

        if (! $employee) {
            abort(403, 'Only employees can request enrollment in training sessions.');
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $enrollment = $this->enrollmentRequestService->submit(
            $session,
            $employee,
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Your enrollment request has been submitted for approval.',
            'enrollment' => new TrainingEnrollmentResource($enrollment),
        ]);
    }

    /**
     * Cancel a pending enrollment request.
     */
    public function cancelEnrollmentRequest(Request $request, TrainingEnrollment $enrollment): JsonResponse
    {
        Gate::authorize('can-view-training');

        $employee = auth()->user()->employee;

        if ($enrollment->employee_id !== $employee?->id) {
            abort(403);
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->enrollmentRequestService->cancel(
            $enrollment,
            $employee,
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Your enrollment request has been cancelled.',
        ]);
    }

    /**
     * Display the employee's enrollments.
     */
    public function myEnrollments(Request $request): InertiaResponse
    {
        Gate::authorize('can-view-training');

        $employee = auth()->user()->employee;

        if (! $employee) {
            return Inertia::render('My/Training/MyEnrollments', [
                'pendingRequests' => [],
                'upcomingEnrollments' => [],
                'pastEnrollments' => [],
                'waitlistEntries' => [],
            ]);
        }

        $pendingRequests = $employee->trainingEnrollments()
            ->with(['session.course', 'approver'])
            ->where('status', EnrollmentStatus::Pending)
            ->upcoming()
            ->orderBy('submitted_at', 'desc')
            ->get();

        $upcomingEnrollments = $employee->trainingEnrollments()
            ->with(['session.course'])
            ->active()
            ->upcoming()
            ->get();

        $pastEnrollments = $employee->trainingEnrollments()
            ->with(['session.course'])
            ->past()
            ->get();

        $waitlistEntries = $employee->activeTrainingWaitlists()
            ->with(['session.course'])
            ->ordered()
            ->get();

        return Inertia::render('My/Training/MyEnrollments', [
            'pendingRequests' => TrainingEnrollmentResource::collection($pendingRequests),
            'upcomingEnrollments' => TrainingEnrollmentResource::collection($upcomingEnrollments),
            'pastEnrollments' => TrainingEnrollmentResource::collection($pastEnrollments),
            'waitlistEntries' => $waitlistEntries->map(fn ($w) => [
                'id' => $w->id,
                'position' => $w->position,
                'joined_at' => $w->joined_at->toISOString(),
                'session' => [
                    'id' => $w->session->id,
                    'display_title' => $w->session->display_title,
                    'date_range' => $w->session->date_range,
                    'course' => [
                        'id' => $w->session->course->id,
                        'title' => $w->session->course->title,
                    ],
                ],
            ]),
        ]);
    }

    /**
     * Export the employee's training calendar as iCal.
     */
    public function exportIcal(): Response
    {
        Gate::authorize('can-view-training');

        $employee = auth()->user()->employee;

        if (! $employee) {
            abort(403);
        }

        $icalContent = $this->icalService->generateForEmployee($employee);

        return response($icalContent)
            ->header('Content-Type', 'text/calendar; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="training-calendar.ics"');
    }
}
