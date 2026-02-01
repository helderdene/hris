<?php

namespace App\Models;

use App\Enums\SyncStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks the synchronization status between an employee and a biometric device.
 *
 * Extends TenantModel for multi-tenant database isolation.
 */
class EmployeeDeviceSync extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeDeviceSyncFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'biometric_device_id',
        'status',
        'last_synced_at',
        'last_attempted_at',
        'last_error',
        'retry_count',
        'last_message_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SyncStatus::class,
            'last_synced_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'retry_count' => 'integer',
        ];
    }

    /**
     * Get the employee this sync record belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the biometric device this sync record belongs to.
     */
    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    /**
     * Scope to get only pending sync records.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SyncStatus::Pending);
    }

    /**
     * Scope to get only failed sync records.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', SyncStatus::Failed);
    }

    /**
     * Scope to get sync records that need to be attempted.
     */
    public function scopeNeedsSync(Builder $query): Builder
    {
        return $query->whereIn('status', [SyncStatus::Pending, SyncStatus::Failed]);
    }

    /**
     * Scope to get sync records for a specific device.
     */
    public function scopeForDevice(Builder $query, int $deviceId): Builder
    {
        return $query->where('biometric_device_id', $deviceId);
    }

    /**
     * Scope to get sync records for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Check if this sync record has an error.
     */
    public function hasError(): bool
    {
        return $this->last_error !== null && $this->last_error !== '';
    }

    /**
     * Mark the sync as in progress.
     */
    public function markSyncing(string $messageId): void
    {
        $this->update([
            'status' => SyncStatus::Syncing,
            'last_attempted_at' => now(),
            'last_message_id' => $messageId,
        ]);
    }

    /**
     * Mark the sync as successful.
     */
    public function markSynced(): void
    {
        $this->update([
            'status' => SyncStatus::Synced,
            'last_synced_at' => now(),
            'last_error' => null,
        ]);
    }

    /**
     * Mark the sync as failed.
     */
    public function markFailed(string $error): void
    {
        $this->update([
            'status' => SyncStatus::Failed,
            'last_error' => $error,
            'retry_count' => $this->retry_count + 1,
        ]);
    }
}
