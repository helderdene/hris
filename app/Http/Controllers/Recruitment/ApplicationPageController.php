<?php

namespace App\Http\Controllers\Recruitment;

use App\Enums\ApplicationStatus;
use App\Enums\AssessmentType;
use App\Enums\BackgroundCheckStatus;
use App\Enums\ReferenceRecommendation;
use App\Http\Controllers\Controller;
use App\Models\Assessment;
use App\Models\BackgroundCheck;
use App\Models\Interview;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\ReferenceCheck;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationPageController extends Controller
{
    /**
     * Display applications for a job posting.
     */
    public function index(Request $request, JobPosting $jobPosting): Response
    {
        $status = $request->input('status');

        $query = JobApplication::query()
            ->forPosting($jobPosting)
            ->with(['candidate'])
            ->orderBy('applied_at', 'desc');

        if ($status) {
            $query->withStatus($status);
        }

        $applications = $query->paginate(25)->through(fn (JobApplication $app) => [
            'id' => $app->id,
            'candidate' => [
                'id' => $app->candidate->id,
                'full_name' => $app->candidate->full_name,
                'email' => $app->candidate->email,
            ],
            'status' => $app->status->value,
            'status_label' => $app->status->label(),
            'status_color' => $app->status->color(),
            'source_label' => $app->source->label(),
            'applied_at' => $app->applied_at?->format('Y-m-d H:i:s'),
            'allowed_transitions' => array_map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'color' => $s->color(),
            ], $app->status->allowedTransitions()),
        ]);

        return Inertia::render('Recruitment/Applications/Index', [
            'jobPosting' => [
                'id' => $jobPosting->id,
                'title' => $jobPosting->title,
            ],
            'applications' => $applications,
            'statuses' => ApplicationStatus::options(),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * Display a specific application.
     */
    public function show(JobApplication $jobApplication): Response
    {
        $jobApplication->load([
            'candidate.education',
            'candidate.workExperiences',
            'jobPosting',
            'assignedToEmployee',
            'statusHistories',
            'interviews.panelists.employee',
            'assessments',
            'backgroundChecks.documents',
            'referenceChecks',
            'offer',
        ]);

        return Inertia::render('Recruitment/Applications/Show', [
            'application' => [
                'id' => $jobApplication->id,
                'candidate' => [
                    'id' => $jobApplication->candidate->id,
                    'full_name' => $jobApplication->candidate->full_name,
                    'email' => $jobApplication->candidate->email,
                    'phone' => $jobApplication->candidate->phone,
                    'resume_file_name' => $jobApplication->candidate->resume_file_name,
                    'skills' => $jobApplication->candidate->skills,
                ],
                'job_posting' => [
                    'id' => $jobApplication->jobPosting->id,
                    'title' => $jobApplication->jobPosting->title,
                    'slug' => $jobApplication->jobPosting->slug,
                ],
                'assigned_to_employee' => $jobApplication->assignedToEmployee ? [
                    'id' => $jobApplication->assignedToEmployee->id,
                    'full_name' => $jobApplication->assignedToEmployee->full_name,
                ] : null,
                'status' => $jobApplication->status->value,
                'status_label' => $jobApplication->status->label(),
                'status_color' => $jobApplication->status->color(),
                'source_label' => $jobApplication->source->label(),
                'cover_letter' => $jobApplication->cover_letter,
                'rejection_reason' => $jobApplication->rejection_reason,
                'notes' => $jobApplication->notes,
                'allowed_transitions' => array_map(fn ($s) => [
                    'value' => $s->value,
                    'label' => $s->label(),
                    'color' => $s->color(),
                ], $jobApplication->status->allowedTransitions()),
                'status_histories' => $jobApplication->statusHistories->map(fn ($h) => [
                    'id' => $h->id,
                    'from_status' => $h->from_status?->value,
                    'from_status_label' => $h->from_status?->label(),
                    'to_status' => $h->to_status->value,
                    'to_status_label' => $h->to_status->label(),
                    'to_status_color' => $h->to_status->color(),
                    'notes' => $h->notes,
                    'created_at' => $h->created_at?->format('Y-m-d H:i:s'),
                ]),
                'applied_at' => $jobApplication->applied_at?->format('Y-m-d H:i:s'),
                'screening_at' => $jobApplication->screening_at?->format('Y-m-d H:i:s'),
                'interview_at' => $jobApplication->interview_at?->format('Y-m-d H:i:s'),
                'assessment_at' => $jobApplication->assessment_at?->format('Y-m-d H:i:s'),
                'offer_at' => $jobApplication->offer_at?->format('Y-m-d H:i:s'),
                'hired_at' => $jobApplication->hired_at?->format('Y-m-d H:i:s'),
                'rejected_at' => $jobApplication->rejected_at?->format('Y-m-d H:i:s'),
                'withdrawn_at' => $jobApplication->withdrawn_at?->format('Y-m-d H:i:s'),
                'created_at' => $jobApplication->created_at?->format('Y-m-d H:i:s'),
            ],
            'statuses' => ApplicationStatus::options(),
            'assessmentTypes' => AssessmentType::options(),
            'backgroundCheckStatuses' => BackgroundCheckStatus::options(),
            'referenceRecommendations' => ReferenceRecommendation::options(),
            'assessments' => $jobApplication->assessments
                ->sortByDesc('assessed_at')
                ->values()
                ->map(fn (Assessment $a) => [
                    'id' => $a->id,
                    'test_name' => $a->test_name,
                    'type' => $a->type->value,
                    'type_label' => $a->type->label(),
                    'type_color' => $a->type->color(),
                    'score' => $a->score,
                    'max_score' => $a->max_score,
                    'passed' => $a->passed,
                    'assessed_at' => $a->assessed_at?->format('Y-m-d H:i:s'),
                    'notes' => $a->notes,
                ]),
            'backgroundChecks' => $jobApplication->backgroundChecks
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (BackgroundCheck $bc) => [
                    'id' => $bc->id,
                    'check_type' => $bc->check_type,
                    'status' => $bc->status->value,
                    'status_label' => $bc->status->label(),
                    'status_color' => $bc->status->color(),
                    'provider' => $bc->provider,
                    'notes' => $bc->notes,
                    'started_at' => $bc->started_at?->format('Y-m-d H:i:s'),
                    'completed_at' => $bc->completed_at?->format('Y-m-d H:i:s'),
                    'documents' => $bc->documents->map(fn ($doc) => [
                        'id' => $doc->id,
                        'file_name' => $doc->file_name,
                        'file_size' => $doc->file_size,
                        'mime_type' => $doc->mime_type,
                    ]),
                ]),
            'referenceChecks' => $jobApplication->referenceChecks
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (ReferenceCheck $rc) => [
                    'id' => $rc->id,
                    'referee_name' => $rc->referee_name,
                    'referee_email' => $rc->referee_email,
                    'referee_phone' => $rc->referee_phone,
                    'referee_company' => $rc->referee_company,
                    'relationship' => $rc->relationship,
                    'contacted' => $rc->contacted,
                    'contacted_at' => $rc->contacted_at?->format('Y-m-d H:i:s'),
                    'feedback' => $rc->feedback,
                    'recommendation' => $rc->recommendation?->value,
                    'recommendation_label' => $rc->recommendation?->label(),
                    'recommendation_color' => $rc->recommendation?->color(),
                    'notes' => $rc->notes,
                ]),
            'interviews' => $jobApplication->interviews
                ->sortByDesc('scheduled_at')
                ->values()
                ->map(fn (Interview $i) => [
                    'id' => $i->id,
                    'type_label' => $i->type->label(),
                    'type_color' => $i->type->color(),
                    'status_label' => $i->status->label(),
                    'status_color' => $i->status->color(),
                    'title' => $i->title,
                    'scheduled_at' => $i->scheduled_at->format('Y-m-d H:i:s'),
                    'duration_minutes' => $i->duration_minutes,
                    'meeting_url' => $i->meeting_url,
                    'panelists' => $i->panelists->map(fn ($p) => [
                        'id' => $p->id,
                        'employee' => [
                            'id' => $p->employee->id,
                            'full_name' => $p->employee->full_name,
                        ],
                        'is_lead' => $p->is_lead,
                    ]),
                ]),
            'offer' => $jobApplication->offer ? [
                'id' => $jobApplication->offer->id,
                'status' => $jobApplication->offer->status->value,
                'status_label' => $jobApplication->offer->status->label(),
                'status_color' => $jobApplication->offer->status->color(),
                'position_title' => $jobApplication->offer->position_title,
                'salary' => $jobApplication->offer->salary,
                'salary_currency' => $jobApplication->offer->salary_currency,
                'sent_at' => $jobApplication->offer->sent_at?->format('Y-m-d H:i:s'),
                'accepted_at' => $jobApplication->offer->accepted_at?->format('Y-m-d H:i:s'),
                'declined_at' => $jobApplication->offer->declined_at?->format('Y-m-d H:i:s'),
            ] : null,
        ]);
    }
}
