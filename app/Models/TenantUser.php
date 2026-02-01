<?php

namespace App\Models;

use App\Enums\TenantUserRole;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class TenantUser extends Pivot
{
    /**
     * The table associated with the model.
     */
    protected $table = 'tenant_user';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = true;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'role',
        'invited_at',
        'invitation_accepted_at',
        'invitation_token',
        'invitation_expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => TenantUserRole::class,
            'invited_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'invitation_expires_at' => 'datetime',
        ];
    }

    /**
     * Get the user that belongs to this tenant membership.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant that this membership belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Check if the invitation is pending (sent but not yet accepted).
     */
    public function isInvitationPending(): bool
    {
        return $this->invited_at !== null
            && $this->invitation_accepted_at === null
            && ! $this->isInvitationExpired();
    }

    /**
     * Check if the invitation has expired.
     */
    public function isInvitationExpired(): bool
    {
        if ($this->invitation_expires_at === null) {
            return false;
        }

        return $this->invitation_expires_at->isPast();
    }

    /**
     * Check if the user has accepted the invitation.
     */
    public function hasAcceptedInvitation(): bool
    {
        return $this->invitation_accepted_at !== null;
    }
}
