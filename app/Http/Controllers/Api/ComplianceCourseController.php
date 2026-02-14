<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreComplianceCourseRequest;
use App\Http\Requests\UpdateComplianceCourseRequest;
use App\Http\Resources\ComplianceCourseListResource;
use App\Http\Resources\ComplianceCourseResource;
use App\Models\ComplianceCourse;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ComplianceCourseController extends Controller
{
    /**
     * Display a listing of compliance courses.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-training');

        $query = ComplianceCourse::query()
            ->with(['course', 'modules', 'assignmentRules'])
            ->whereHas('course')
            ->orderBy('created_at', 'desc');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('course', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('published_only')) {
            $query->published();
        }

        if ($request->boolean('requires_recertification')) {
            $query->requiresRecertification();
        }

        $courses = $query->get();

        return ComplianceCourseListResource::collection($courses);
    }

    /**
     * Store a newly created compliance course.
     */
    public function store(StoreComplianceCourseRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-training');

        return DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $validated['created_by'] = auth()->user()->employee?->id;

            // Mark the parent course as compliance
            $course = Course::findOrFail($validated['course_id']);
            $course->update(['is_compliance' => true]);

            $complianceCourse = ComplianceCourse::create($validated);
            $complianceCourse->load(['course', 'modules', 'creator']);

            return (new ComplianceCourseResource($complianceCourse))
                ->response()
                ->setStatusCode(201);
        });
    }

    /**
     * Display the specified compliance course.
     */
    public function show(ComplianceCourse $complianceCourse): ComplianceCourseResource
    {
        Gate::authorize('can-view-training');

        $complianceCourse->load([
            'course.categories',
            'modules.assessments',
            'assignmentRules',
            'creator',
        ]);

        return new ComplianceCourseResource($complianceCourse);
    }

    /**
     * Update the specified compliance course.
     */
    public function update(
        UpdateComplianceCourseRequest $request,
        ComplianceCourse $complianceCourse
    ): ComplianceCourseResource {
        Gate::authorize('can-manage-training');

        $validated = $request->validated();
        $complianceCourse->update($validated);

        $complianceCourse->load([
            'course.categories',
            'modules.assessments',
            'assignmentRules',
            'creator',
        ]);

        return new ComplianceCourseResource($complianceCourse);
    }

    /**
     * Remove compliance settings from a course.
     */
    public function destroy(ComplianceCourse $complianceCourse): JsonResponse
    {
        Gate::authorize('can-manage-training');

        // Check for active assignments
        $activeAssignments = $complianceCourse->assignments()->active()->count();

        if ($activeAssignments > 0) {
            return response()->json([
                'message' => "Cannot delete compliance settings. There are {$activeAssignments} active assignments.",
            ], 422);
        }

        DB::transaction(function () use ($complianceCourse) {
            // Remove is_compliance flag from parent course
            $complianceCourse->course->update(['is_compliance' => false]);

            // Soft delete the compliance course
            $complianceCourse->delete();
        });

        return response()->json([
            'message' => 'Compliance settings removed successfully.',
        ]);
    }

    /**
     * Get statistics for a compliance course.
     */
    public function statistics(ComplianceCourse $complianceCourse): JsonResponse
    {
        Gate::authorize('can-manage-training');

        $assignments = $complianceCourse->assignments();

        $stats = [
            'total_assignments' => $assignments->count(),
            'pending' => $assignments->pending()->count(),
            'in_progress' => $assignments->inProgress()->count(),
            'completed' => $assignments->completed()->count(),
            'overdue' => $assignments->overdue()->count(),
            'exempted' => $assignments->byStatus('exempted')->count(),
            'completion_rate' => $this->calculateCompletionRate($complianceCourse),
            'average_score' => $this->calculateAverageScore($complianceCourse),
            'average_completion_days' => $this->calculateAverageCompletionDays($complianceCourse),
        ];

        return response()->json($stats);
    }

    /**
     * Calculate completion rate for a compliance course.
     */
    protected function calculateCompletionRate(ComplianceCourse $complianceCourse): float
    {
        $total = $complianceCourse->assignments()
            ->whereNotIn('status', ['exempted'])
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $completed = $complianceCourse->assignments()->completed()->count();

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Calculate average score for completed assignments.
     */
    protected function calculateAverageScore(ComplianceCourse $complianceCourse): ?float
    {
        $avg = $complianceCourse->assignments()
            ->completed()
            ->whereNotNull('final_score')
            ->avg('final_score');

        return $avg !== null ? round((float) $avg, 2) : null;
    }

    /**
     * Calculate average days to complete.
     */
    protected function calculateAverageCompletionDays(ComplianceCourse $complianceCourse): ?float
    {
        $completedAssignments = $complianceCourse->assignments()
            ->completed()
            ->whereNotNull('completed_at')
            ->get();

        if ($completedAssignments->isEmpty()) {
            return null;
        }

        $totalDays = $completedAssignments->sum(function ($assignment) {
            return $assignment->assigned_date->diffInDays($assignment->completed_at);
        });

        return round($totalDays / $completedAssignments->count(), 1);
    }
}
