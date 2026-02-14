<?php

namespace App\Http\Controllers\Recruitment;

use App\Enums\InterviewStatus;
use App\Enums\InterviewType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Interview;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class InterviewPageController extends Controller
{
    /**
     * Display all interviews across applications.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');

        $query = Interview::query()
            ->with(['jobApplication.candidate', 'jobApplication.jobPosting', 'panelists.employee'])
            ->orderBy('scheduled_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $interviews = $query->paginate(25)->through(fn (Interview $interview) => array_merge(
            $this->formatInterview($interview),
            [
                'candidate' => [
                    'id' => $interview->jobApplication->candidate->id,
                    'full_name' => $interview->jobApplication->candidate->full_name,
                ],
                'job_posting' => [
                    'id' => $interview->jobApplication->jobPosting->id,
                    'title' => $interview->jobApplication->jobPosting->title,
                ],
                'job_application_id' => $interview->job_application_id,
            ],
        ));

        return Inertia::render('Recruitment/Interviews/IndexAll', [
            'interviews' => $interviews,
            'interviewTypes' => InterviewType::options(),
            'interviewStatuses' => InterviewStatus::options(),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * Display interviews for a job application.
     */
    public function forApplication(JobApplication $jobApplication): Response
    {
        $jobApplication->load(['candidate', 'jobPosting']);

        $interviews = $jobApplication->interviews()
            ->with(['panelists.employee'])
            ->orderBy('scheduled_at', 'desc')
            ->get()
            ->map(fn (Interview $interview) => $this->formatInterview($interview));

        return Inertia::render('Recruitment/Interviews/Index', [
            'jobApplication' => [
                'id' => $jobApplication->id,
                'candidate' => [
                    'id' => $jobApplication->candidate->id,
                    'full_name' => $jobApplication->candidate->full_name,
                ],
                'job_posting' => [
                    'id' => $jobApplication->jobPosting->id,
                    'title' => $jobApplication->jobPosting->title,
                ],
            ],
            'interviews' => $interviews,
            'interviewTypes' => InterviewType::options(),
            'interviewStatuses' => InterviewStatus::options(),
            'employees' => Employee::query()
                ->active()
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'full_name' => $e->full_name,
                ]),
        ]);
    }

    /**
     * Display a specific interview.
     */
    public function show(Interview $interview): Response
    {
        $interview->load([
            'jobApplication.candidate',
            'jobApplication.jobPosting',
            'panelists.employee',
            'createdByEmployee',
        ]);

        return Inertia::render('Recruitment/Interviews/Show', [
            'interview' => $this->formatInterview($interview),
            'jobApplication' => [
                'id' => $interview->jobApplication->id,
                'candidate' => [
                    'id' => $interview->jobApplication->candidate->id,
                    'full_name' => $interview->jobApplication->candidate->full_name,
                    'email' => $interview->jobApplication->candidate->email,
                ],
                'job_posting' => [
                    'id' => $interview->jobApplication->jobPosting->id,
                    'title' => $interview->jobApplication->jobPosting->title,
                ],
            ],
            'interviewTypes' => InterviewType::options(),
            'interviewStatuses' => InterviewStatus::options(),
            'employees' => Employee::query()
                ->active()
                ->orderBy('first_name')
                ->get()
                ->map(fn (Employee $e) => [
                    'id' => $e->id,
                    'full_name' => $e->full_name,
                ]),
        ]);
    }

    /**
     * Format an interview for the frontend.
     *
     * @return array<string, mixed>
     */
    protected function formatInterview(Interview $interview): array
    {
        return [
            'id' => $interview->id,
            'type' => $interview->type->value,
            'type_label' => $interview->type->label(),
            'type_color' => $interview->type->color(),
            'status' => $interview->status->value,
            'status_label' => $interview->status->label(),
            'status_color' => $interview->status->color(),
            'title' => $interview->title,
            'scheduled_at' => $interview->scheduled_at->format('Y-m-d H:i:s'),
            'duration_minutes' => $interview->duration_minutes,
            'location' => $interview->location,
            'meeting_url' => $interview->meeting_url,
            'notes' => $interview->notes,
            'cancelled_at' => $interview->cancelled_at?->format('Y-m-d H:i:s'),
            'cancellation_reason' => $interview->cancellation_reason,
            'created_by' => $interview->createdByEmployee ? [
                'id' => $interview->createdByEmployee->id,
                'full_name' => $interview->createdByEmployee->full_name,
            ] : null,
            'panelists' => $interview->panelists->map(fn ($p) => [
                'id' => $p->id,
                'employee' => [
                    'id' => $p->employee->id,
                    'full_name' => $p->employee->full_name,
                ],
                'is_lead' => $p->is_lead,
                'invitation_sent_at' => $p->invitation_sent_at?->format('Y-m-d H:i:s'),
                'feedback' => $p->feedback,
                'rating' => $p->rating,
                'feedback_submitted_at' => $p->feedback_submitted_at?->format('Y-m-d H:i:s'),
            ]),
            'created_at' => $interview->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
