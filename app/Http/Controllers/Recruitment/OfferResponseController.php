<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Services\Recruitment\OfferService;
use Inertia\Inertia;
use Inertia\Response;

class OfferResponseController extends Controller
{
    /**
     * Display the public offer response page for candidates.
     */
    public function show(string $tenant, Offer $offer, OfferService $offerService): Response
    {
        // Record that the candidate viewed the offer
        $offerService->recordView($offer);

        $offer->load(['jobApplication.candidate', 'jobApplication.jobPosting']);

        return Inertia::render('Public/OfferResponse', [
            'offer' => [
                'id' => $offer->id,
                'content' => $offer->content,
                'status' => $offer->status->value,
                'status_label' => $offer->status->label(),
                'salary' => $offer->salary,
                'salary_currency' => $offer->salary_currency,
                'salary_frequency' => $offer->salary_frequency,
                'benefits' => $offer->benefits,
                'start_date' => $offer->start_date?->format('Y-m-d'),
                'expiry_date' => $offer->expiry_date?->format('Y-m-d'),
                'position_title' => $offer->position_title,
                'department' => $offer->department,
                'work_location' => $offer->work_location,
                'employment_type' => $offer->employment_type,
                'candidate_name' => $offer->jobApplication->candidate->full_name,
                'candidate_email' => $offer->jobApplication->candidate->email,
                'company_name' => $offer->jobApplication->jobPosting->title,
            ],
        ]);
    }
}
