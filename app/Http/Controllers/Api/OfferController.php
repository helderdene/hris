<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptOfferRequest;
use App\Http\Requests\DeclineOfferRequest;
use App\Http\Requests\RevokeOfferRequest;
use App\Http\Requests\StoreOfferRequest;
use App\Http\Requests\UpdateOfferRequest;
use App\Http\Resources\OfferResource;
use App\Models\Offer;
use App\Services\Recruitment\OfferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class OfferController extends Controller
{
    public function __construct(
        protected OfferService $offerService
    ) {}

    /**
     * Display a listing of offers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = Offer::query()
            ->with(['jobApplication.candidate', 'jobApplication.jobPosting'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('job_application_id')) {
            $query->where('job_application_id', $request->input('job_application_id'));
        }

        return OfferResource::collection($query->paginate(25));
    }

    /**
     * Store a newly created offer.
     */
    public function store(StoreOfferRequest $request): RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $offer = $this->offerService->createOffer($request->validated());

        return redirect("/recruitment/offers/{$offer->id}");
    }

    /**
     * Display the specified offer.
     */
    public function show(string $tenant, Offer $offer): OfferResource
    {
        $offer->load(['jobApplication.candidate', 'jobApplication.jobPosting', 'offerTemplate', 'signatures']);

        return new OfferResource($offer);
    }

    /**
     * Update the specified offer.
     */
    public function update(UpdateOfferRequest $request, string $tenant, Offer $offer): OfferResource
    {
        Gate::authorize('can-manage-organization');

        $offer->update($request->validated());

        return new OfferResource($offer->fresh());
    }

    /**
     * Remove the specified offer.
     */
    public function destroy(string $tenant, Offer $offer): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $offer->delete();

        return response()->json(['message' => 'Offer deleted successfully.']);
    }

    /**
     * Send the offer to the candidate.
     */
    public function send(string $tenant, Offer $offer): RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $this->offerService->sendOffer($offer);

        return redirect()->back();
    }

    /**
     * Accept the offer (public, via signed URL).
     */
    public function accept(AcceptOfferRequest $request, string $tenant, Offer $offer): RedirectResponse
    {
        $this->offerService->acceptOffer($offer, array_merge(
            $request->validated(),
            [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]
        ));

        return redirect()->back();
    }

    /**
     * Decline the offer (public, via signed URL).
     */
    public function decline(DeclineOfferRequest $request, string $tenant, Offer $offer): RedirectResponse
    {
        $this->offerService->declineOffer($offer, $request->validated('reason'));

        return redirect()->back();
    }

    /**
     * Revoke the offer.
     */
    public function revoke(RevokeOfferRequest $request, string $tenant, Offer $offer): RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $this->offerService->revokeOffer($offer, $request->validated('reason'));

        return redirect()->back();
    }

    /**
     * Download the offer PDF.
     */
    public function downloadPdf(string $tenant, Offer $offer): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        if (! $offer->pdf_path || ! Storage::disk('local')->exists($offer->pdf_path)) {
            $this->offerService->generatePdf($offer);
            $offer->refresh();
        }

        return Storage::disk('local')->download(
            $offer->pdf_path,
            "offer-{$offer->id}.pdf"
        );
    }

    /**
     * Preview the resolved offer content.
     */
    public function preview(Request $request, string $tenant, Offer $offer): JsonResponse
    {
        return response()->json([
            'content' => $offer->content,
        ]);
    }
}
