<?php

namespace App\Models;

use App\Enums\CertificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Certification model for tracking employee professional certifications.
 *
 * Supports workflow from draft through approval to active status,
 * with automatic expiry tracking and notification reminders.
 */
class Certification extends TenantModel
{
    /** @use HasFactory<\Database\Factories\CertificationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'certification_type_id',
        'certificate_number',
        'issuing_body',
        'issued_date',
        'expiry_date',
        'description',
        'status',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'revoked_at',
        'rejection_reason',
        'revocation_reason',
        'metadata',
        'created_by',
        'approved_by',
        'rejected_by',
        'revoked_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_date' => 'date',
            'expiry_date' => 'date',
            'status' => CertificationStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Certification $certification) {
            if ($certification->status === null) {
                $certification->status = CertificationStatus::Draft;
            }
        });
    }

    /**
     * Get the employee who holds this certification.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the certification type.
     */
    public function certificationType(): BelongsTo
    {
        return $this->belongsTo(CertificationType::class);
    }

    /**
     * Get the files attached to this certification.
     */
    public function files(): HasMany
    {
        return $this->hasMany(CertificationFile::class);
    }

    /**
     * Scope to filter certifications for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by status.
     */
    public function scopeWithStatus(Builder $query, CertificationStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter active certifications.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', CertificationStatus::Active);
    }

    /**
     * Scope to filter certifications expiring within days.
     */
    public function scopeExpiringWithin(Builder $query, int $days): Builder
    {
        return $query
            ->where('status', CertificationStatus::Active)
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now(), now()->addDays($days)]);
    }

    /**
     * Scope to filter certifications expiring exactly on a date.
     */
    public function scopeExpiringOn(Builder $query, \DateTimeInterface $date): Builder
    {
        return $query
            ->where('status', CertificationStatus::Active)
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', $date);
    }

    /**
     * Scope to filter expired certifications.
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query
            ->where('status', CertificationStatus::Active)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->startOfDay());
    }

    /**
     * Scope to filter pending approval certifications.
     */
    public function scopePendingApproval(Builder $query): Builder
    {
        return $query->where('status', CertificationStatus::PendingApproval);
    }

    /**
     * Check if the certification can be edited.
     */
    protected function canBeEdited(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeEdited()
        );
    }

    /**
     * Check if the certification can be submitted.
     */
    protected function canBeSubmitted(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeSubmitted() && $this->files()->exists()
        );
    }

    /**
     * Check if the certification is expiring soon (within 30 days).
     */
    protected function isExpiringSoon(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->expiry_date || $this->status !== CertificationStatus::Active) {
                    return false;
                }

                return $this->expiry_date->isBetween(now(), now()->addDays(30));
            }
        );
    }

    /**
     * Get days until expiry.
     */
    protected function daysUntilExpiry(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->expiry_date) {
                    return null;
                }

                return now()->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
            }
        );
    }

    /**
     * Submit the certification for approval.
     */
    public function submit(): bool
    {
        if (! $this->can_be_submitted) {
            return false;
        }

        $this->status = CertificationStatus::PendingApproval;
        $this->submitted_at = now();

        return $this->save();
    }

    /**
     * Approve the certification.
     */
    public function approve(int $approvedBy): bool
    {
        if (! $this->status->canTransitionTo(CertificationStatus::Active)) {
            return false;
        }

        $this->status = CertificationStatus::Active;
        $this->approved_at = now();
        $this->approved_by = $approvedBy;

        return $this->save();
    }

    /**
     * Reject the certification.
     */
    public function reject(int $rejectedBy, string $reason): bool
    {
        if (! $this->status->canTransitionTo(CertificationStatus::Draft)) {
            return false;
        }

        $this->status = CertificationStatus::Draft;
        $this->rejected_at = now();
        $this->rejected_by = $rejectedBy;
        $this->rejection_reason = $reason;

        return $this->save();
    }

    /**
     * Revoke the certification.
     */
    public function revoke(int $revokedBy, string $reason): bool
    {
        if (! $this->status->canTransitionTo(CertificationStatus::Revoked)) {
            return false;
        }

        $this->status = CertificationStatus::Revoked;
        $this->revoked_at = now();
        $this->revoked_by = $revokedBy;
        $this->revocation_reason = $reason;

        return $this->save();
    }

    /**
     * Mark the certification as expired.
     */
    public function markAsExpired(): bool
    {
        if (! $this->status->canTransitionTo(CertificationStatus::Expired)) {
            return false;
        }

        $this->status = CertificationStatus::Expired;

        return $this->save();
    }
}
