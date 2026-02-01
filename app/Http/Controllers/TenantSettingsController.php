<?php

namespace App\Http\Controllers;

use App\Http\Requests\TenantLogoUploadRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class TenantSettingsController extends Controller
{
    /**
     * Store a new logo for the tenant.
     *
     * Uploads the logo to tenant-specific storage path and updates the tenant record.
     * Route binding uses 'tenantModel' to avoid conflict with subdomain {tenant} parameter.
     */
    public function storeLogo(TenantLogoUploadRequest $request, Tenant $tenantModel): RedirectResponse
    {
        // Verify user has admin access to this tenant
        $user = $request->user();

        if (! $user->isAdminInTenant($tenantModel)) {
            abort(403, 'You do not have permission to update tenant settings.');
        }

        // Delete old logo if exists
        if ($tenantModel->logo_path) {
            $oldPath = str_replace('/storage/', '', $tenantModel->logo_path);
            Storage::disk('public')->delete($oldPath);
        }

        // Store new logo in tenant-specific path
        $file = $request->file('logo');
        $extension = $file->getClientOriginalExtension();
        $filename = 'logo.'.$extension;
        $path = "tenants/{$tenantModel->slug}/branding";

        // Store the file
        $file->storeAs($path, $filename, 'public');

        // Update tenant record with new logo path
        $tenantModel->update([
            'logo_path' => "/storage/{$path}/{$filename}",
        ]);

        return redirect()->back()->with('success', 'Logo updated successfully.');
    }

    /**
     * Remove the tenant's logo.
     *
     * Route binding uses 'tenantModel' to avoid conflict with subdomain {tenant} parameter.
     */
    public function destroyLogo(Tenant $tenantModel): RedirectResponse
    {
        $user = request()->user();

        if (! $user->isAdminInTenant($tenantModel)) {
            abort(403, 'You do not have permission to update tenant settings.');
        }

        if ($tenantModel->logo_path) {
            $oldPath = str_replace('/storage/', '', $tenantModel->logo_path);
            Storage::disk('public')->delete($oldPath);

            $tenantModel->update([
                'logo_path' => null,
            ]);
        }

        return redirect()->back()->with('success', 'Logo removed successfully.');
    }
}
