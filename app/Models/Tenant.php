<?php

namespace App\Models;

use App\Enums\AddonType;
use App\Enums\Module;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'primary_color',
        'timezone',
        'business_info',
        'payroll_settings',
        'leave_defaults',
        'plan_id',
        'paymongo_customer_id',
        'trial_ends_at',
        'trial_expired_notified_at',
        'employee_count_cache',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_info' => 'array',
            'payroll_settings' => 'array',
            'leave_defaults' => 'array',
            'trial_ends_at' => 'datetime',
            'trial_expired_notified_at' => 'datetime',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Tenant $tenant) {
            if (empty($tenant->timezone)) {
                $tenant->timezone = 'Asia/Manila';
            }
        });
    }

    /**
     * Get the database connection for the model.
     * Uses 'platform' connection in production/MySQL environments,
     * but falls back to default for SQLite/testing.
     */
    public function getConnectionName(): ?string
    {
        $defaultConnection = config('database.default');

        if ($defaultConnection === 'sqlite') {
            return null;
        }

        return 'platform';
    }

    /**
     * Get the database schema name for this tenant.
     */
    public function getDatabaseName(): string
    {
        return 'kasamahr_tenant_'.$this->slug;
    }

    /**
     * Check if the slug is URL-safe.
     */
    public static function isValidSlug(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
    }

    /**
     * Get the users that belong to the tenant.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->using(TenantUser::class)
            ->withPivot([
                'role',
                'invited_at',
                'invitation_accepted_at',
                'invitation_token',
                'invitation_expires_at',
            ])
            ->withTimestamps();
    }

    /**
     * Get the double holiday pay rate from payroll settings.
     *
     * Returns the configured double holiday rate percentage, or 300% as the default.
     */
    public function getDoubleHolidayRate(): int
    {
        return $this->payroll_settings['double_holiday_rate'] ?? 300;
    }

    /**
     * Get the plan assigned to this tenant.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get all subscriptions for this tenant.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get a specific subscription by name.
     */
    public function subscription(string $name = 'default'): ?Subscription
    {
        return $this->subscriptions()->where('name', $name)->first();
    }

    /**
     * Check if the tenant has an active subscription by name.
     */
    public function subscribed(string $name = 'default'): bool
    {
        $subscription = $this->subscription($name);

        return $subscription !== null && $subscription->active();
    }

    /**
     * Check if the tenant is currently on a trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the tenant's trial has expired.
     */
    public function trialExpired(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    /**
     * Check if the tenant has active access (trial or subscription).
     */
    public function hasActiveAccess(): bool
    {
        return $this->onTrial() || $this->subscribed('default');
    }

    /**
     * Get all add-ons for this tenant.
     */
    public function addons(): HasMany
    {
        return $this->hasMany(TenantAddon::class);
    }

    /**
     * Get active (non-expired) add-ons for this tenant.
     */
    public function activeAddons(): HasMany
    {
        return $this->addons()->active();
    }

    /**
     * Check if the tenant's plan includes a given module.
     */
    public function hasModule(Module $module): bool
    {
        if (! $this->plan) {
            return false;
        }

        return $this->plan->hasModule($module);
    }

    /**
     * Get all available module values for the tenant's plan.
     *
     * @return array<string>
     */
    public function availableModules(): array
    {
        if (! $this->plan) {
            return [];
        }

        return $this->plan->modules->pluck('module')->toArray();
    }

    /**
     * Get effective limit including add-ons.
     * Returns null if no plan, -1 for unlimited.
     */
    public function effectiveLimit(string $key): ?int
    {
        $base = $this->plan?->getLimit($key);

        if ($base === null || $base === -1) {
            return $base;
        }

        $addonType = match ($key) {
            'max_employees' => AddonType::EmployeeSlots,
            'max_biometric_devices' => AddonType::BiometricDevices,
            default => null,
        };

        if (! $addonType) {
            return $base;
        }

        $extra = $this->activeAddons()
            ->where('type', $addonType->value)
            ->get()
            ->sum(fn (TenantAddon $addon) => $addon->extraUnits());

        return $base + $extra;
    }
}
