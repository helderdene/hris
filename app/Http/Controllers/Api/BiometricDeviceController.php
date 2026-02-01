<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBiometricDeviceRequest;
use App\Http\Requests\UpdateBiometricDeviceRequest;
use App\Http\Resources\BiometricDeviceResource;
use App\Models\BiometricDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class BiometricDeviceController extends Controller
{
    /**
     * Display a listing of biometric devices.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('can-manage-organization');

        $query = BiometricDevice::query()
            ->with('workLocation')
            ->orderBy('name');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by work location
        if ($request->filled('work_location_id')) {
            $query->where('work_location_id', $request->input('work_location_id'));
        }

        $devices = $query->get();

        return BiometricDeviceResource::collection($devices);
    }

    /**
     * Store a newly created biometric device.
     */
    public function store(StoreBiometricDeviceRequest $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $device = BiometricDevice::create($request->validated());
        $device->load('workLocation');

        return (new BiometricDeviceResource($device))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Display the specified biometric device.
     */
    public function show(Request $request): BiometricDeviceResource
    {
        Gate::authorize('can-manage-organization');

        $device = BiometricDevice::findOrFail($request->route('deviceId'));
        $device->load('workLocation');

        return new BiometricDeviceResource($device);
    }

    /**
     * Update the specified biometric device.
     */
    public function update(UpdateBiometricDeviceRequest $request): BiometricDeviceResource
    {
        Gate::authorize('can-manage-organization');

        $device = BiometricDevice::findOrFail($request->route('deviceId'));
        $device->update($request->validated());
        $device->load('workLocation');

        return new BiometricDeviceResource($device);
    }

    /**
     * Remove the specified biometric device.
     */
    public function destroy(Request $request): JsonResponse
    {
        Gate::authorize('can-manage-organization');

        $device = BiometricDevice::findOrFail($request->route('deviceId'));
        $device->delete();

        return response()->json([
            'message' => 'Biometric device deleted successfully.',
        ]);
    }
}
