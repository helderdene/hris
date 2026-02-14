<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingEnrollmentRequest;
use App\Http\Resources\TrainingEnrollmentResource;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Models\TrainingSession;
use App\Services\Training\EnrollmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TrainingEnrollmentController extends Controller
{
    public function __construct(
        protected EnrollmentService $enrollmentService
    ) {}

    /**
     * Display a listing of enrollments for a session.
     */
    public function index(Request $request, TrainingSession $session): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-training');

        $enrollments = $session->enrollments()
            ->with(['employee.department', 'employee.position', 'enrolledByEmployee', 'cancelledByEmployee'])
            ->orderBy('enrolled_at')
            ->get();

        return TrainingEnrollmentResource::collection($enrollments);
    }

    /**
     * Store a new enrollment.
     */
    public function store(StoreTrainingEnrollmentRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $session = TrainingSession::findOrFail($request->input('training_session_id'));
        $employee = Employee::findOrFail($request->input('employee_id'));
        $enrolledBy = auth()->user()->employee;

        $result = $this->enrollmentService->enroll(
            $session,
            $employee,
            $enrolledBy,
            $request->input('notes')
        );

        // Check if result is enrollment or waitlist
        if ($result instanceof TrainingEnrollment) {
            $result->load(['employee.department', 'employee.position', 'session.course']);

            return (new TrainingEnrollmentResource($result))
                ->response()
                ->setStatusCode(201);
        }

        // If waitlisted
        return response()->json([
            'message' => 'Session is full. Employee has been added to the waitlist.',
            'waitlist_position' => $result->position,
        ], 201);
    }

    /**
     * Display the specified enrollment.
     */
    public function show(TrainingEnrollment $enrollment): TrainingEnrollmentResource
    {
        Gate::authorize('can-view-training');

        // Allow employees to view their own enrollments
        $employee = auth()->user()->employee;
        if (! Gate::allows('can-manage-training') && $enrollment->employee_id !== $employee?->id) {
            abort(403);
        }

        $enrollment->load(['employee.department', 'employee.position', 'session.course']);

        return new TrainingEnrollmentResource($enrollment);
    }

    /**
     * Cancel an enrollment.
     */
    public function destroy(Request $request, TrainingEnrollment $enrollment): JsonResponse
    {
        // Allow employees to cancel their own enrollments
        $employee = auth()->user()->employee;
        $isOwn = $enrollment->employee_id === $employee?->id;

        if (! $isOwn && ! Gate::allows('can-manage-training')) {
            abort(403);
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->enrollmentService->cancelEnrollment(
            $enrollment,
            $employee,
            $request->input('reason')
        );

        return response()->json([
            'message' => 'Enrollment cancelled successfully.',
        ]);
    }

    /**
     * Mark an enrollment as attended.
     */
    public function markAttended(TrainingEnrollment $enrollment): TrainingEnrollmentResource
    {
        Gate::authorize('can-manage-training');

        $this->enrollmentService->markAsAttended($enrollment);
        $enrollment->load(['employee.department', 'employee.position']);

        return new TrainingEnrollmentResource($enrollment);
    }

    /**
     * Mark an enrollment as no-show.
     */
    public function markNoShow(TrainingEnrollment $enrollment): TrainingEnrollmentResource
    {
        Gate::authorize('can-manage-training');

        $this->enrollmentService->markAsNoShow($enrollment);
        $enrollment->load(['employee.department', 'employee.position']);

        return new TrainingEnrollmentResource($enrollment);
    }

    /**
     * Bulk enroll multiple employees.
     */
    public function bulkEnroll(Request $request, TrainingSession $session): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $request->validate([
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employees,id'],
        ]);

        $enrolledBy = auth()->user()->employee;
        $results = $this->enrollmentService->bulkEnroll(
            $session,
            $request->input('employee_ids'),
            $enrolledBy
        );

        $enrolled = collect($results)->filter(fn ($r) => $r instanceof TrainingEnrollment)->count();
        $waitlisted = count($results) - $enrolled;

        return response()->json([
            'message' => "Enrolled {$enrolled} employees. {$waitlisted} added to waitlist.",
            'enrolled_count' => $enrolled,
            'waitlisted_count' => $waitlisted,
        ]);
    }
}
