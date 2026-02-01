<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Logs individual sync operations (commands) sent to biometric devices.
 *
 * Extends TenantModel for multi-tenant database isolation.
 */
class DeviceSyncLog extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DeviceSyncLogFactory> */
    use HasFactory;

    /**
     * Operation types for sync logs.
     */
    public const OPERATION_EDIT_PERSON = 'edit_person';

    public const OPERATION_DELETE_PERSON = 'delete_person';

    /**
     * Status values for sync logs.
     */
    public const STATUS_SENT = 'sent';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_FAILED = 'failed';

    public const STATUS_TIMEOUT = 'timeout';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'biometric_device_id',
        'operation',
        'message_id',
        'status',
        'request_payload',
        'response_payload',
        'error_message',
        'sent_at',
        'acknowledged_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
            'sent_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /**
     * Get the employee this sync log belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the biometric device this sync log belongs to.
     */
    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    /**
     * Check if this log entry was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_ACKNOWLEDGED;
    }

    /**
     * Check if this log entry indicates a failure.
     */
    public function isFailed(): bool
    {
        return in_array($this->status, [self::STATUS_FAILED, self::STATUS_TIMEOUT], true);
    }

    /**
     * Mark the log as acknowledged.
     *
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function markAcknowledged(?array $responsePayload = null): void
    {
        $this->update([
            'status' => self::STATUS_ACKNOWLEDGED,
            'acknowledged_at' => now(),
            'response_payload' => $responsePayload,
        ]);
    }

    /**
     * Mark the log as failed.
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark the log as timed out.
     */
    public function markTimeout(): void
    {
        $this->update([
            'status' => self::STATUS_TIMEOUT,
            'error_message' => 'Device did not acknowledge within timeout period',
        ]);
    }
}
