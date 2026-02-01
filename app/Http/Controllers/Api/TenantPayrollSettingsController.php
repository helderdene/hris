<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePayrollSettingsRequest;
use Illuminate\Http\JsonResponse;

class TenantPayrollSettingsController extends Controller
{
    /**
     * Get the current tenant's payroll settings.
     */
    public function show(): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $payrollSettings = $tenant->payroll_settings ?? [];

        // Ensure default values are returned
        $settings = [
            'pay_frequency' => $payrollSettings['pay_frequency'] ?? 'semi-monthly',
            'cutoff_day' => $payrollSettings['cutoff_day'] ?? 15,
            'double_holiday_rate' => $payrollSettings['double_holiday_rate'] ?? 300,
        ];

        return response()->json($settings);
    }

    /**
     * Update the current tenant's payroll settings.
     *
     * Only tenant admins can update payroll settings.
     */
    public function update(UpdatePayrollSettingsRequest $request): JsonResponse
    {
        $tenant = tenant();

        if (! $tenant) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $validated = $request->validated();
        $currentSettings = $tenant->payroll_settings ?? [];

        // Merge validated data with existing settings
        $updatedSettings = array_merge($currentSettings, $validated);

        $tenant->update(['payroll_settings' => $updatedSettings]);

        return response()->json($updatedSettings);
    }
}
