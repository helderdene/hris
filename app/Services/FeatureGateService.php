<?php

namespace App\Services;

use App\Enums\Module;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantUserRole;
use App\Models\BiometricDevice;
use App\Models\Employee;
use App\Models\Kiosk;
use App\Models\Tenant;
use Carbon\Carbon;

class FeatureGateService
{
    public function __construct(private Tenant $tenant) {}

    /**
     * Check if the tenant's plan includes a given module.
     */
    public function hasModule(Module $module): bool
    {
        return $this->tenant->hasModule($module);
    }

    /**
     * Check if the tenant has active access (trial or subscription).
     */
    public function hasActiveAccess(): bool
    {
        return $this->tenant->hasActiveAccess();
    }

    /**
     * Get all available module values for the tenant's plan.
     *
     * @return array<string>
     */
    public function availableModules(): array
    {
        return $this->tenant->availableModules();
    }

    /**
     * Get the current plan slug.
     */
    public function currentPlanSlug(): ?string
    {
        return $this->tenant->plan?->slug;
    }

    /**
     * Get the current subscription status.
     */
    public function subscriptionStatus(): ?SubscriptionStatus
    {
        return $this->tenant->subscription('default')?->paymongo_status;
    }

    /**
     * Check if the tenant is currently on a trial.
     */
    public function isOnTrial(): bool
    {
        return $this->tenant->onTrial();
    }

    /**
     * Get the trial end date.
     */
    public function trialEndsAt(): ?Carbon
    {
        return $this->tenant->trial_ends_at;
    }

    /**
     * Check if the tenant is within its employee limit.
     */
    public function isWithinEmployeeLimit(): bool
    {
        $limit = $this->getEffectiveLimit('max_employees');

        if ($limit === null || $limit === -1) {
            return true;
        }

        return Employee::where('employment_status', 'active')->count() < $limit;
    }

    /**
     * Check if the tenant is within its admin/HR user limit.
     */
    public function isWithinUserLimit(): bool
    {
        $limit = $this->getEffectiveLimit('max_admin_users');

        if ($limit === null || $limit === -1) {
            return true;
        }

        $adminRoles = [
            TenantUserRole::Admin->value,
            TenantUserRole::HrManager->value,
            TenantUserRole::HrStaff->value,
            TenantUserRole::HrConsultant->value,
        ];

        $count = $this->tenant->users()
            ->wherePivotIn('role', $adminRoles)
            ->count();

        return $count < $limit;
    }

    /**
     * Check if the tenant is within its biometric device limit.
     */
    public function isWithinDeviceLimit(): bool
    {
        $limit = $this->getEffectiveLimit('max_biometric_devices');

        if ($limit === null || $limit === -1) {
            return true;
        }

        return BiometricDevice::count() < $limit;
    }

    /**
     * Check if the tenant is within its kiosk limit.
     */
    public function isWithinKioskLimit(): bool
    {
        $limit = $this->getEffectiveLimit('max_kiosks');

        if ($limit === null || $limit === -1) {
            return true;
        }

        return Kiosk::count() < $limit;
    }

    /**
     * Get a specific limit from the tenant's plan.
     */
    public function getLimit(string $key, mixed $default = null): mixed
    {
        return $this->tenant->plan?->getLimit($key, $default);
    }

    /**
     * Get the effective limit including add-ons.
     */
    public function getEffectiveLimit(string $key): ?int
    {
        return $this->tenant->effectiveLimit($key);
    }

    /**
     * Build an array suitable for sharing via Inertia.
     *
     * @return array{plan: ?string, status: ?string, is_on_trial: bool, trial_ends_at: ?string, available_modules: array<string>}
     */
    public function toShareableArray(): array
    {
        return [
            'plan' => $this->currentPlanSlug(),
            'status' => $this->subscriptionStatus()?->value,
            'is_on_trial' => $this->isOnTrial(),
            'trial_ends_at' => $this->trialEndsAt()?->toISOString(),
            'available_modules' => array_values($this->availableModules()),
        ];
    }
}
