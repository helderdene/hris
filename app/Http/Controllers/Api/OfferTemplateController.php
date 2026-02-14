<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOfferTemplateRequest;
use App\Http\Requests\UpdateOfferTemplateRequest;
use App\Http\Resources\OfferTemplateResource;
use App\Models\OfferTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OfferTemplateController extends Controller
{
    /**
     * Display a listing of offer templates.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = OfferTemplate::query()
            ->orderBy('name');

        if ($request->boolean('active_only', false)) {
            $query->active();
        }

        return OfferTemplateResource::collection($query->paginate(25));
    }

    /**
     * Store a newly created offer template.
     */
    public function store(StoreOfferTemplateRequest $request): RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        OfferTemplate::create(array_merge(
            $request->validated(),
            ['created_by' => auth()->id()]
        ));

        return redirect('/recruitment/offer-templates');
    }

    /**
     * Display the specified offer template.
     */
    public function show(OfferTemplate $offerTemplate): OfferTemplateResource
    {
        return new OfferTemplateResource($offerTemplate);
    }

    /**
     * Update the specified offer template.
     */
    public function update(UpdateOfferTemplateRequest $request, OfferTemplate $offerTemplate): RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $offerTemplate->update($request->validated());

        return redirect('/recruitment/offer-templates');
    }

    /**
     * Remove the specified offer template.
     */
    public function destroy(OfferTemplate $offerTemplate): RedirectResponse
    {
        Gate::authorize('can-manage-organization');

        $offerTemplate->delete();

        return redirect('/recruitment/offer-templates');
    }
}
