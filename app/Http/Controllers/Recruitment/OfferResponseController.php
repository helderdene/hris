<?php

namespace App\Http\Controllers\Recruitment;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptOfferRequest;
use App\Http\Requests\DeclineOfferRequest;
use App\Models\Offer;
use App\Services\Recruitment\OfferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class OfferResponseController extends Controller
{
    /**
     * Display the public offer response page for candidates.
     */
    public function show(Offer $offer, OfferService $offerService): Response
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
                'accept_url' => $this->signedUrl('offers.respond.accept', $offer),
                'decline_url' => $this->signedUrl('offers.respond.decline', $offer),
            ],
        ]);
    }

    /**
     * Accept the offer from the candidate's signed link.
     */
    public function accept(AcceptOfferRequest $request, Offer $offer, OfferService $offerService): RedirectResponse
    {
        $offerService->acceptOffer($offer, array_merge($request->validated(), [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]));

        return redirect()->back();
    }

    /**
     * Decline the offer from the candidate's signed link.
     */
    public function decline(DeclineOfferRequest $request, Offer $offer, OfferService $offerService): RedirectResponse
    {
        $offerService->declineOffer($offer, $request->validated('reason'));

        return redirect()->back();
    }

    /**
     * Build a signed link the candidate can post their response to.
     */
    protected function signedUrl(string $route, Offer $offer): string
    {
        return URL::temporarySignedRoute($route, now()->addDays(30), [
            'tenant' => tenant()->slug,
            'offer' => $offer->id,
        ]);
    }
}
