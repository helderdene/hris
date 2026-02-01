<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AttendanceLog model for storing raw biometric attendance events.
 *
 * Captures real-time attendance data from MQTT-connected facial recognition
 * devices including verification confidence and captured photos.
 */
class AttendanceLog extends TenantModel
{
    /** @use HasFactory<\Database\Factories\AttendanceLogFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'biometric_device_id',
        'employee_id',
        'device_person_id',
        'device_record_id',
        'employee_code',
        'confidence',
        'verify_status',
        'logged_at',
        'direction',
        'person_name',
        'captured_photo',
        'raw_payload',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'logged_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    /**
     * Get the biometric device that recorded this log.
     */
    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    /**
     * Get the employee associated with this log.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
