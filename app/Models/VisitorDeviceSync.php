<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VisitorDeviceSync model tracking the sync status of visitor photos to FR devices.
 */
class VisitorDeviceSync extends TenantModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'visitor_id',
        'biometric_device_id',
        'status',
        'last_synced_at',
        'last_error',
        'message_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Get the visitor for this sync record.
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Get the biometric device for this sync record.
     */
    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    /**
     * Mark this sync as in progress.
     */
    public function markSyncing(): void
    {
        $this->update(['status' => 'syncing']);
    }

    /**
     * Mark this sync as successfully completed.
     */
    public function markSynced(): void
    {
        $this->update([
            'status' => 'synced',
            'last_synced_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark this sync as failed with an error message.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'last_error' => $error,
        ]);
    }
}
