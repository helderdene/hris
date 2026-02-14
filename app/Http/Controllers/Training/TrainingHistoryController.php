<?php

namespace App\Http\Controllers\Training;

use App\Enums\CompletionStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseListResource;
use App\Http\Resources\TrainingHistoryResource;
use App\Models\Course;
use App\Models\Employee;
use App\Models\TrainingEnrollment;
use App\Services\Training\TrainingHistoryExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrainingHistoryController extends Controller
{
    /**
     * Display the training history index page.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('can-manage-training');

        $query = TrainingEnrollment::query()
            ->with([
                'employee.department',
                'employee.position',
                'session.course',
                'session.instructor',
            ])
            ->whereHas('session', fn ($q) => $q->whereNotNull('start_date'))
            ->orderByDesc(function ($query) {
                $query->select('start_date')
                    ->from('training_sessions')
                    ->whereColumn('training_sessions.id', 'training_enrollments.training_session_id')
                    ->limit(1);
            });

        $this->applyFilters($query, $request);

        $enrollments = $query->paginate(25)->withQueryString();

        return Inertia::render('Training/History/Index', [
            'enrollments' => TrainingHistoryResource::collection($enrollments),
            'courses' => CourseListResource::collection($this->getCourses()),
            'trainers' => $this->getTrainers(),
            'locations' => $this->getLocations(),
            'filters' => [
                'search' => $request->input('search'),
                'course_id' => $request->input('course_id'),
                'trainer_id' => $request->input('trainer_id'),
                'status' => $request->input('status'),
                'completion_status' => $request->input('completion_status'),
                'location' => $request->input('location'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'employee_id' => $request->input('employee_id'),
            ],
            'statusOptions' => $this->getEnrollmentStatusOptions(),
            'completionStatusOptions' => $this->getCompletionStatusOptions(),
        ]);
    }

    /**
     * Display training history for a specific employee.
     */
    public function employeeHistory(Employee $employee, Request $request): Response
    {
        Gate::authorize('can-manage-training');

        $query = TrainingEnrollment::query()
            ->with([
                'session.course',
                'session.instructor',
            ])
            ->forEmployee($employee)
            ->whereHas('session', fn ($q) => $q->whereNotNull('start_date'))
            ->orderByDesc(function ($query) {
                $query->select('start_date')
                    ->from('training_sessions')
                    ->whereColumn('training_sessions.id', 'training_enrollments.training_session_id')
                    ->limit(1);
            });

        $this->applyFilters($query, $request);

        $enrollments = $query->paginate(25)->withQueryString();

        $employee->load(['department', 'position']);

        return Inertia::render('Training/History/Employee', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'department' => $employee->department?->name,
                'position' => $employee->position?->name,
            ],
            'enrollments' => TrainingHistoryResource::collection($enrollments),
            'courses' => CourseListResource::collection($this->getCourses()),
            'trainers' => $this->getTrainers(),
            'filters' => [
                'course_id' => $request->input('course_id'),
                'status' => $request->input('status'),
                'completion_status' => $request->input('completion_status'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
            ],
            'statusOptions' => $this->getEnrollmentStatusOptions(),
            'completionStatusOptions' => $this->getCompletionStatusOptions(),
        ]);
    }

    /**
     * Export training history to Excel.
     */
    public function export(Request $request, TrainingHistoryExportService $exportService): StreamedResponse
    {
        Gate::authorize('can-manage-training');

        $query = TrainingEnrollment::query()
            ->with([
                'employee.department',
                'employee.position',
                'session.course',
                'session.instructor',
            ])
            ->whereHas('session', fn ($q) => $q->whereNotNull('start_date'))
            ->orderByDesc(function ($query) {
                $query->select('start_date')
                    ->from('training_sessions')
                    ->whereColumn('training_sessions.id', 'training_enrollments.training_session_id')
                    ->limit(1);
            });

        $this->applyFilters($query, $request);

        $enrollments = $query->get();

        return $exportService->export($enrollments);
    }

    /**
     * Apply filters to the query.
     */
    private function applyFilters($query, Request $request): void
    {
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereHas('employee', function ($eq) use ($search) {
                    $eq->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%");
                })
                    ->orWhereHas('session', function ($sq) use ($search) {
                        $sq->where('title', 'like', "%{$search}%")
                            ->orWhereHas('course', fn ($cq) => $cq->where('title', 'like', "%{$search}%"));
                    });
            });
        }

        if ($request->filled('employee_id')) {
            $query->forEmployee((int) $request->input('employee_id'));
        }

        if ($request->filled('course_id')) {
            $query->whereHas('session', fn ($q) => $q->where('course_id', $request->input('course_id')));
        }

        if ($request->filled('trainer_id')) {
            $query->whereHas('session', fn ($q) => $q->where('instructor_employee_id', $request->input('trainer_id')));
        }

        if ($request->filled('status')) {
            $status = EnrollmentStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status->value);
            }
        }

        if ($request->filled('completion_status')) {
            $completionStatus = CompletionStatus::tryFrom($request->input('completion_status'));
            if ($completionStatus) {
                $query->byCompletionStatus($completionStatus);
            }
        }

        if ($request->filled('location')) {
            $query->whereHas('session', fn ($q) => $q->where('location', $request->input('location')));
        }

        if ($request->filled('date_from')) {
            $query->whereHas('session', fn ($q) => $q->where('start_date', '>=', $request->input('date_from')));
        }

        if ($request->filled('date_to')) {
            $query->whereHas('session', fn ($q) => $q->where('end_date', '<=', $request->input('date_to')));
        }
    }

    /**
     * Get published courses for filter dropdown.
     */
    private function getCourses(): \Illuminate\Database\Eloquent\Collection
    {
        return Course::query()
            ->orderBy('title')
            ->get(['id', 'title', 'code']);
    }

    /**
     * Get trainers (instructors) for filter dropdown.
     *
     * @return array<int, array{id: int, full_name: string}>
     */
    private function getTrainers(): array
    {
        $instructorIds = \App\Models\TrainingSession::query()
            ->whereNotNull('instructor_employee_id')
            ->distinct()
            ->pluck('instructor_employee_id');

        return Employee::query()
            ->whereIn('id', $instructorIds)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name'])
            ->map(fn ($e) => [
                'id' => $e->id,
                'full_name' => $e->full_name,
            ])
            ->values()
            ->toArray();
    }

    /**
     * Get unique locations for filter dropdown.
     *
     * @return array<string>
     */
    private function getLocations(): array
    {
        return \App\Models\TrainingSession::query()
            ->whereNotNull('location')
            ->distinct()
            ->pluck('location')
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Get enrollment status options for frontend.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function getEnrollmentStatusOptions(): array
    {
        return array_map(
            fn (EnrollmentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->badgeColor(),
            ],
            EnrollmentStatus::cases()
        );
    }

    /**
     * Get completion status options for frontend.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function getCompletionStatusOptions(): array
    {
        return array_map(
            fn (CompletionStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->badgeColor(),
            ],
            CompletionStatus::cases()
        );
    }
}
