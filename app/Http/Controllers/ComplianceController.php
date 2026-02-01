<?php

namespace App\Http\Controllers;

use App\Enums\ComplianceAssignmentStatus;
use App\Enums\ComplianceModuleContentType;
use App\Enums\ComplianceRuleType;
use App\Http\Resources\ComplianceAssignmentResource;
use App\Http\Resources\ComplianceCourseListResource;
use App\Http\Resources\ComplianceCourseResource;
use App\Http\Resources\ComplianceRuleResource;
use App\Models\ComplianceAssignment;
use App\Models\ComplianceAssignmentRule;
use App\Models\ComplianceCourse;
use App\Models\Course;
use App\Models\Department;
use App\Models\Position;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ComplianceController extends Controller
{
    /**
     * Display the compliance dashboard.
     */
    public function dashboard(): Response
    {
        Gate::authorize('can-view-compliance');

        $stats = $this->getDashboardStats();

        return Inertia::render('Compliance/Dashboard', [
            'stats' => $stats,
        ]);
    }

    /**
     * Display the compliance courses index.
     */
    public function coursesIndex(Request $request): Response
    {
        Gate::authorize('can-manage-compliance');

        $query = ComplianceCourse::query()
            ->with(['course'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('course', fn ($q) => $q->where('title', 'like', "%{$search}%"));
        }

        $courses = $query->get();

        return Inertia::render('Compliance/Courses/Index', [
            'courses' => ComplianceCourseListResource::collection($courses),
            'filters' => [
                'search' => $request->input('search'),
            ],
            'contentTypeOptions' => $this->getContentTypeOptions(),
        ]);
    }

    /**
     * Display the compliance course detail page.
     */
    public function coursesShow(string $tenant, ComplianceCourse $complianceCourse): Response
    {
        Gate::authorize('can-manage-compliance');

        $complianceCourse->load([
            'course',
            'modules' => fn ($q) => $q->orderBy('sort_order'),
            'modules.assessments',
            'assignmentRules' => fn ($q) => $q->orderBy('priority'),
        ]);

        $availableCourses = Course::query()
            ->where('is_compliance', false)
            ->whereDoesntHave('complianceCourse')
            ->orderBy('title')
            ->get(['id', 'title', 'code']);

        return Inertia::render('Compliance/Courses/Show', [
            'complianceCourse' => new ComplianceCourseResource($complianceCourse),
            'availableCourses' => $availableCourses,
            'contentTypeOptions' => $this->getContentTypeOptions(),
            'ruleTypeOptions' => $this->getRuleTypeOptions(),
        ]);
    }

    /**
     * Display the compliance course creation page.
     */
    public function coursesCreate(): Response
    {
        Gate::authorize('can-manage-compliance');

        $availableCourses = Course::query()
            ->where('is_compliance', false)
            ->whereDoesntHave('complianceCourse')
            ->orderBy('title')
            ->get(['id', 'title', 'code']);

        return Inertia::render('Compliance/Courses/Create', [
            'availableCourses' => $availableCourses,
            'contentTypeOptions' => $this->getContentTypeOptions(),
        ]);
    }

    /**
     * Display the assignment rules index.
     */
    public function rulesIndex(Request $request): Response
    {
        Gate::authorize('can-manage-compliance');

        $query = ComplianceAssignmentRule::query()
            ->with(['complianceCourse.course', 'creator'])
            ->orderBy('priority');

        if ($request->filled('compliance_course_id')) {
            $query->where('compliance_course_id', $request->input('compliance_course_id'));
        }

        if ($request->filled('is_active')) {
            $query->active();
        }

        $rules = $query->get();

        $courses = ComplianceCourse::query()
            ->with('course')
            ->get();

        return Inertia::render('Compliance/Rules/Index', [
            'rules' => ComplianceRuleResource::collection($rules),
            'courses' => ComplianceCourseListResource::collection($courses),
            'filters' => [
                'compliance_course_id' => $request->input('compliance_course_id'),
                'is_active' => $request->input('is_active'),
            ],
            'ruleTypeOptions' => $this->getRuleTypeOptions(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'positions' => Position::orderBy('title')->get(['id', 'title']),
        ]);
    }

    /**
     * Display the assignments index.
     */
    public function assignmentsIndex(Request $request): Response
    {
        Gate::authorize('can-manage-compliance');

        $query = ComplianceAssignment::query()
            ->with(['employee', 'complianceCourse.course', 'assignmentRule'])
            ->orderBy('due_date');

        if ($request->filled('status')) {
            $status = ComplianceAssignmentStatus::tryFrom($request->input('status'));
            if ($status) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('compliance_course_id')) {
            $query->where('compliance_course_id', $request->input('compliance_course_id'));
        }

        if ($request->filled('department_id')) {
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $request->input('department_id')));
        }

        if ($request->filled('is_overdue')) {
            $query->overdue();
        }

        $assignments = $query->paginate(25);

        $courses = ComplianceCourse::query()
            ->with('course')
            ->get();

        $employees = \App\Models\Employee::query()
            ->orderBy('first_name')
            ->get(['id', 'employee_number', 'first_name', 'last_name']);

        return Inertia::render('Compliance/Assignments/Index', [
            'assignments' => ComplianceAssignmentResource::collection($assignments->items()),
            'pagination' => [
                'current_page' => $assignments->currentPage(),
                'last_page' => $assignments->lastPage(),
                'per_page' => $assignments->perPage(),
                'total' => $assignments->total(),
            ],
            'courses' => ComplianceCourseListResource::collection($courses),
            'employees' => $employees->map(fn ($e) => [
                'id' => $e->id,
                'employee_number' => $e->employee_number,
                'full_name' => $e->first_name.' '.$e->last_name,
            ]),
            'filters' => [
                'status' => $request->input('status'),
                'compliance_course_id' => $request->input('compliance_course_id'),
                'department_id' => $request->input('department_id'),
                'is_overdue' => $request->input('is_overdue'),
            ],
            'statusOptions' => $this->getStatusOptions(),
            'departments' => Department::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Display the compliance reports page.
     */
    public function reportsIndex(): Response
    {
        Gate::authorize('can-view-compliance');

        $departmentStats = Department::query()
            ->withCount([
                'employees as total_employees',
                'employees as compliant_employees' => fn ($q) => $q->whereHas('complianceAssignments', fn ($q2) => $q2->where('status', ComplianceAssignmentStatus::Completed)),
            ])
            ->orderBy('name')
            ->get()
            ->map(fn ($dept) => [
                'id' => $dept->id,
                'name' => $dept->name,
                'total_employees' => $dept->total_employees,
                'compliant_employees' => $dept->compliant_employees,
                'compliance_rate' => $dept->total_employees > 0
                    ? round(($dept->compliant_employees / $dept->total_employees) * 100, 1)
                    : 0,
            ]);

        return Inertia::render('Compliance/Reports/Index', [
            'departmentStats' => $departmentStats,
        ]);
    }

    /**
     * Get dashboard statistics.
     *
     * @return array<string, mixed>
     */
    private function getDashboardStats(): array
    {
        $totalAssignments = ComplianceAssignment::count();
        $completedAssignments = ComplianceAssignment::where('status', ComplianceAssignmentStatus::Completed)->count();
        $overdueAssignments = ComplianceAssignment::where('status', ComplianceAssignmentStatus::Overdue)->count();
        $inProgressAssignments = ComplianceAssignment::where('status', ComplianceAssignmentStatus::InProgress)->count();
        $pendingAssignments = ComplianceAssignment::where('status', ComplianceAssignmentStatus::Pending)->count();

        return [
            'total_assignments' => $totalAssignments,
            'completed_assignments' => $completedAssignments,
            'overdue_assignments' => $overdueAssignments,
            'in_progress_assignments' => $inProgressAssignments,
            'pending_assignments' => $pendingAssignments,
            'compliance_rate' => $totalAssignments > 0
                ? round(($completedAssignments / $totalAssignments) * 100, 1)
                : 0,
            'active_courses' => ComplianceCourse::count(),
            'active_rules' => ComplianceAssignmentRule::active()->count(),
        ];
    }

    /**
     * Get status options for frontend.
     *
     * @return array<int, array{value: string, label: string, color: string}>
     */
    private function getStatusOptions(): array
    {
        return array_map(
            fn (ComplianceAssignmentStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ],
            ComplianceAssignmentStatus::cases()
        );
    }

    /**
     * Get content type options for frontend.
     *
     * @return array<int, array{value: string, label: string, icon: string}>
     */
    private function getContentTypeOptions(): array
    {
        return array_map(
            fn (ComplianceModuleContentType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'icon' => $type->icon(),
            ],
            ComplianceModuleContentType::cases()
        );
    }

    /**
     * Get rule type options for frontend.
     *
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function getRuleTypeOptions(): array
    {
        return array_map(
            fn (ComplianceRuleType $type) => [
                'value' => $type->value,
                'label' => $type->label(),
                'description' => $type->description(),
            ],
            ComplianceRuleType::cases()
        );
    }
}
