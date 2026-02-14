<?php

namespace App\Http\Controllers\Recruitment;

use App\Enums\OfferStatus;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\Offer;
use App\Models\OfferTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OfferPageController extends Controller
{
    /**
     * Display a listing of offers.
     */
    public function index(Request $request): Response
    {
        $status = $request->input('status');

        $query = Offer::query()
            ->with(['jobApplication.candidate', 'jobApplication.jobPosting'])
            ->orderBy('created_at', 'desc');

        if ($status) {
            $query->where('status', $status);
        }

        $offers = $query->paginate(25)->through(fn (Offer $offer) => [
            'id' => $offer->id,
            'candidate_name' => $offer->jobApplication->candidate->full_name,
            'job_title' => $offer->jobApplication->jobPosting->title,
            'position_title' => $offer->position_title,
            'salary' => $offer->salary,
            'salary_currency' => $offer->salary_currency,
            'status' => $offer->status->value,
            'status_label' => $offer->status->label(),
            'status_color' => $offer->status->color(),
            'start_date' => $offer->start_date?->format('Y-m-d'),
            'expiry_date' => $offer->expiry_date?->format('Y-m-d'),
            'sent_at' => $offer->sent_at?->format('Y-m-d H:i:s'),
            'created_at' => $offer->created_at?->format('Y-m-d H:i:s'),
        ]);

        return Inertia::render('Recruitment/Offers/Index', [
            'offers' => $offers,
            'statuses' => OfferStatus::options(),
            'filters' => [
                'status' => $status,
            ],
        ]);
    }

    /**
     * Show the form for creating a new offer.
     */
    public function create(Request $request): Response
    {
        $jobApplication = null;

        if ($request->filled('job_application_id')) {
            $jobApplication = JobApplication::with(['candidate', 'jobPosting'])
                ->findOrFail($request->input('job_application_id'));
        }

        $templates = OfferTemplate::active()
            ->orderBy('name')
            ->get()
            ->map(fn (OfferTemplate $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'content' => $t->content,
                'is_default' => $t->is_default,
            ]);

        $jobApplications = JobApplication::with(['candidate', 'jobPosting'])
            ->whereDoesntHave('offer')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (JobApplication $app) => [
                'id' => $app->id,
                'label' => $app->candidate->full_name.' — '.$app->jobPosting->title,
                'candidate' => [
                    'id' => $app->candidate->id,
                    'full_name' => $app->candidate->full_name,
                    'email' => $app->candidate->email,
                ],
                'job_posting' => [
                    'id' => $app->jobPosting->id,
                    'title' => $app->jobPosting->title,
                ],
            ]);

        return Inertia::render('Recruitment/Offers/Create', [
            'jobApplication' => $jobApplication ? [
                'id' => $jobApplication->id,
                'candidate' => [
                    'id' => $jobApplication->candidate->id,
                    'full_name' => $jobApplication->candidate->full_name,
                    'email' => $jobApplication->candidate->email,
                ],
                'job_posting' => [
                    'id' => $jobApplication->jobPosting->id,
                    'title' => $jobApplication->jobPosting->title,
                ],
            ] : null,
            'jobApplications' => $jobApplications,
            'templates' => $templates,
        ]);
    }

    /**
     * Display the specified offer.
     */
    public function show(Offer $offer): Response
    {
        $offer->load([
            'jobApplication.candidate',
            'jobApplication.jobPosting',
            'offerTemplate',
            'signatures',
        ]);

        return Inertia::render('Recruitment/Offers/Show', [
            'offer' => [
                'id' => $offer->id,
                'job_application' => [
                    'id' => $offer->jobApplication->id,
                    'candidate' => [
                        'id' => $offer->jobApplication->candidate->id,
                        'full_name' => $offer->jobApplication->candidate->full_name,
                        'email' => $offer->jobApplication->candidate->email,
                    ],
                    'job_posting' => [
                        'id' => $offer->jobApplication->jobPosting->id,
                        'title' => $offer->jobApplication->jobPosting->title,
                    ],
                ],
                'offer_template' => $offer->offerTemplate ? [
                    'id' => $offer->offerTemplate->id,
                    'name' => $offer->offerTemplate->name,
                ] : null,
                'content' => $offer->content,
                'status' => $offer->status->value,
                'status_label' => $offer->status->label(),
                'status_color' => $offer->status->color(),
                'allowed_transitions' => array_map(fn ($s) => [
                    'value' => $s->value,
                    'label' => $s->label(),
                    'color' => $s->color(),
                ], $offer->status->allowedTransitions()),
                'salary' => $offer->salary,
                'salary_currency' => $offer->salary_currency,
                'salary_frequency' => $offer->salary_frequency,
                'benefits' => $offer->benefits,
                'terms' => $offer->terms,
                'start_date' => $offer->start_date?->format('Y-m-d'),
                'expiry_date' => $offer->expiry_date?->format('Y-m-d'),
                'position_title' => $offer->position_title,
                'department' => $offer->department,
                'work_location' => $offer->work_location,
                'employment_type' => $offer->employment_type,
                'pdf_path' => $offer->pdf_path,
                'decline_reason' => $offer->decline_reason,
                'revoke_reason' => $offer->revoke_reason,
                'signatures' => $offer->signatures->map(fn ($sig) => [
                    'id' => $sig->id,
                    'signer_type' => $sig->signer_type,
                    'signer_name' => $sig->signer_name,
                    'signer_email' => $sig->signer_email,
                    'signed_at' => $sig->signed_at?->format('Y-m-d H:i:s'),
                ]),
                'sent_at' => $offer->sent_at?->format('Y-m-d H:i:s'),
                'viewed_at' => $offer->viewed_at?->format('Y-m-d H:i:s'),
                'accepted_at' => $offer->accepted_at?->format('Y-m-d H:i:s'),
                'declined_at' => $offer->declined_at?->format('Y-m-d H:i:s'),
                'expired_at' => $offer->expired_at?->format('Y-m-d H:i:s'),
                'revoked_at' => $offer->revoked_at?->format('Y-m-d H:i:s'),
                'created_at' => $offer->created_at?->format('Y-m-d H:i:s'),
            ],
            'statuses' => OfferStatus::options(),
        ]);
    }
}
