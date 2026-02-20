<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'paymongo_id',
        'paymongo_plan_id',
        'paymongo_status',
        'plan_price_id',
        'quantity',
        'current_period_end',
        'ends_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paymongo_status' => SubscriptionStatus::class,
            'current_period_end' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Get the database connection for the model.
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
     * Get the tenant that owns this subscription.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the plan price for this subscription.
     */
    public function planPrice(): BelongsTo
    {
        return $this->belongsTo(PlanPrice::class);
    }

    /**
     * Check if this subscription is currently active.
     */
    public function active(): bool
    {
        return $this->paymongo_status === SubscriptionStatus::Active
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Check if this subscription has been cancelled.
     */
    public function cancelled(): bool
    {
        return $this->ends_at !== null;
    }

    /**
     * Check if this subscription is on a grace period (cancelled but not yet expired).
     */
    public function onGracePeriod(): bool
    {
        return $this->cancelled() && $this->ends_at->isFuture();
    }
}
