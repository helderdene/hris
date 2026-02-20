<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreKioskRequest;
use App\Http\Requests\UpdateKioskRequest;
use App\Http\Resources\KioskResource;
use App\Models\Kiosk;
use App\Services\FeatureGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    /**
     * Display a listing of kiosks.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = Kiosk::query()
            ->with('workLocation')
            ->orderBy('name');

        if ($request->filled('work_location_id')) {
            $query->where('work_location_id', $request->input('work_location_id'));
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        return KioskResource::collection($query->get());
    }

    /**
     * Store a newly created kiosk.
     */
    public function store(StoreKioskRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $gate = app(FeatureGateService::class);
        if (! $gate->isWithinKioskLimit()) {
            return response()->json([
                'message' => "You've reached your kiosk limit ({$gate->getEffectiveLimit('max_kiosks')}). "
                    .'You can purchase additional kiosk slots or upgrade your plan.',
            ], 422);
        }

        $kiosk = Kiosk::create([
            ...$request->validated(),
            'token' => Str::random(64),
        ]);

        $kiosk->load('workLocation');

        return (new KioskResource($kiosk))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified kiosk.
     */
    public function show(Kiosk $kiosk): KioskResource
    {
        Gate::authorize('can-manage-organization');

        $kiosk->load('workLocation');

        return new KioskResource($kiosk);
    }

    /**
     * Update the specified kiosk.
     */
    public function update(UpdateKioskRequest $request, Kiosk $kiosk): KioskResource
    {
        Gate::authorize('can-manage-organization');

        $kiosk->update($request->validated());
        $kiosk->load('workLocation');

        return new KioskResource($kiosk);
    }

    /**
     * Remove the specified kiosk.
     */
    public function destroy(Kiosk $kiosk): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $kiosk->delete();

        return response()->json([
            'message' => 'Kiosk deleted successfully.',
        ]);
    }

    /**
     * Regenerate the kiosk's access token.
     */
    public function regenerateToken(Kiosk $kiosk): KioskResource
    {
        Gate::authorize('can-manage-organization');

        $kiosk->update(['token' => Str::random(64)]);
        $kiosk->load('workLocation');

        return new KioskResource($kiosk);
    }
}
