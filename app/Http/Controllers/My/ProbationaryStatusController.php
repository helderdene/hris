<?php

namespace App\Http\Controllers\My;

use App\Enums\EmploymentType;
use App\Enums\ProbationaryEvaluationStatus;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ProbationaryEvaluation;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProbationaryStatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $employee = $user ? Employee::with(['position', 'department'])
            ->where('user_id', $user->id)
            ->first() : null;

        if (! $employee) {
            abort(404, 'Employee profile not found.');
        }

        $evaluations = ProbationaryEvaluation::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [
                ProbationaryEvaluationStatus::Approved,
                ProbationaryEvaluationStatus::Submitted,
                ProbationaryEvaluationStatus::HrReview,
                ProbationaryEvaluationStatus::Draft,
                ProbationaryEvaluationStatus::Pending,
                ProbationaryEvaluationStatus::RevisionRequested,
            ])
            ->orderBy('milestone')
            ->get()
            ->map(fn (ProbationaryEvaluation $evaluation) => [
                'id' => $evaluation->id,
                'milestone' => $evaluation->milestone->value,
                'milestone_label' => $evaluation->milestone->label(),
                'status' => $evaluation->status->value,
                'status_label' => $evaluation->status->label(),
                'status_color' => $evaluation->status->color(),
                'milestone_date' => $evaluation->milestone_date?->toDateString(),
                'due_date' => $evaluation->due_date?->toDateString(),
                'overall_rating' => $evaluation->overall_rating,
                'strengths' => $evaluation->status === ProbationaryEvaluationStatus::Approved
                    ? $evaluation->strengths
                    : null,
                'areas_for_improvement' => $evaluation->status === ProbationaryEvaluationStatus::Approved
                    ? $evaluation->areas_for_improvement
                    : null,
                'recommendation' => $evaluation->recommendation?->value,
                'recommendation_label' => $evaluation->recommendation?->label(),
                'approved_at' => $evaluation->approved_at?->toDateString(),
            ])
            ->toArray();

        // Calculate probation progress
        $probationEndDate = null;
        $daysRemaining = null;
        $probationProgress = 0;

        if ($employee->employment_type === EmploymentType::Probationary && $employee->hire_date) {
            $hireDate = $employee->hire_date;
            $probationEndDate = $hireDate->copy()->addMonths(6);
            $totalDays = $hireDate->diffInDays($probationEndDate);
            $daysPassed = $hireDate->diffInDays(now());

            $daysRemaining = max(0, $probationEndDate->diffInDays(now(), false));
            $probationProgress = min(100, ($daysPassed / $totalDays) * 100);
        }

        return Inertia::render('My/ProbationaryStatus', [
            'employee' => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'employment_type' => $employee->employment_type->value,
                'employment_type_label' => $employee->employment_type->label(),
                'hire_date' => $employee->hire_date?->toDateString(),
                'regularization_date' => $employee->regularization_date,
                'position' => $employee->position?->name,
                'department' => $employee->department?->name,
            ],
            'evaluations' => $evaluations,
            'probation_end_date' => $probationEndDate?->toDateString(),
            'days_remaining' => $daysRemaining,
            'probation_progress' => $probationProgress,
        ]);
    }
}
