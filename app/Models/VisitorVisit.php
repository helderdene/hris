<?php

namespace App\Models;

use App\Enums\CheckInMethod;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * VisitorVisit model tracking individual visit instances for visitors.
 */
class VisitorVisit extends TenantModel
{
    /** @use HasFactory<\Database\Factories\VisitorVisitFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'visitor_id',
        'work_location_id',
        'host_employee_id',
        'purpose',
        'status',
        'registration_source',
        'expected_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejection_reason',
        'checked_in_at',
        'checked_out_at',
        'check_in_method',
        'checked_in_by',
        'biometric_device_id',
        'kiosk_id',
        'qr_token',
        'registration_token',
        'badge_number',
        'host_notified_at',
        'host_approved_at',
        'host_approved_by',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'check_in_method' => CheckInMethod::class,
            'expected_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
            'host_notified_at' => 'datetime',
            'host_approved_at' => 'datetime',
        ];
    }

    /**
     * Get the visitor for this visit.
     */
    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    /**
     * Get the work location for this visit.
     */
    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * Get the host employee for this visit.
     */
    public function hostEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'host_employee_id');
    }

    /**
     * Get the user who approved this visit.
     */
    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who host-approved this visit.
     */
    public function hostApprovedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_approved_by');
    }

    /**
     * Check if both admin and host have approved this visit.
     */
    public function isFullyApproved(): bool
    {
        return $this->approved_at !== null && $this->host_approved_at !== null;
    }

    /**
     * Scope to get visits for a specific host employee.
     */
    public function scopeForHost(Builder $query, int $employeeId): Builder
    {
        return $query->where('host_employee_id', $employeeId);
    }

    /**
     * Get the user who manually checked in this visitor.
     */
    public function checkedInByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /**
     * Get the biometric device used for check-in.
     */
    public function biometricDevice(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class);
    }

    /**
     * Get the kiosk used for check-in.
     */
    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    /**
     * Scope to get active visits (checked in but not checked out).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', VisitStatus::CheckedIn);
    }

    /**
     * Scope to get today's visits.
     */
    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('expected_at', today());
    }

    /**
     * Scope to get visits at a specific location.
     */
    public function scopeAtLocation(Builder $query, int $locationId): Builder
    {
        return $query->where('work_location_id', $locationId);
    }

    /**
     * Scope to get pending approval visits.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', VisitStatus::PendingApproval);
    }
}
