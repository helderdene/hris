<?php

namespace App\Models;

use App\Enums\OvertimeApprovalDecision;
use App\Enums\OvertimeRequestStatus;
use App\Enums\OvertimeType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * OvertimeRequest model for employee overtime requests.
 *
 * Supports multi-level approval workflow with DTR linking on final approval.
 */
class OvertimeRequest extends TenantModel
{
    /** @use HasFactory<\Database\Factories\OvertimeRequestFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'daily_time_record_id',
        'reference_number',
        'overtime_date',
        'expected_start_time',
        'expected_end_time',
        'expected_minutes',
        'overtime_type',
        'reason',
        'status',
        'current_approval_level',
        'total_approval_levels',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'cancelled_at',
        'cancellation_reason',
        'metadata',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'overtime_date' => 'date',
            'expected_minutes' => 'integer',
            'overtime_type' => OvertimeType::class,
            'status' => OvertimeRequestStatus::class,
            'current_approval_level' => 'integer',
            'total_approval_levels' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Boot the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (OvertimeRequest $request) {
            if (empty($request->reference_number)) {
                $request->reference_number = self::generateReferenceNumber();
            }

            if ($request->status === null) {
                $request->status = OvertimeRequestStatus::Draft;
            }
        });
    }

    /**
     * Get the employee who submitted this request.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the linked daily time record.
     */
    public function dailyTimeRecord(): BelongsTo
    {
        return $this->belongsTo(DailyTimeRecord::class);
    }

    /**
     * Get all approval records for this request.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(OvertimeRequestApproval::class)->orderBy('approval_level');
    }

    /**
     * Get the current pending approval.
     */
    public function currentApproval(): HasOne
    {
        return $this->hasOne(OvertimeRequestApproval::class)
            ->where('approval_level', $this->current_approval_level)
            ->where('decision', OvertimeApprovalDecision::Pending);
    }

    /**
     * Scope to filter requests for a specific employee.
     */
    public function scopeForEmployee(Builder $query, int|Employee $employee): Builder
    {
        $employeeId = $employee instanceof Employee ? $employee->id : $employee;

        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter only pending requests.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', OvertimeRequestStatus::Pending);
    }

    /**
     * Scope to filter only approved requests.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', OvertimeRequestStatus::Approved);
    }

    /**
     * Scope to filter requests for a specific date.
     */
    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('overtime_date', $date);
    }

    /**
     * Scope to filter requests pending approval by a specific approver.
     */
    public function scopeForApprover(Builder $query, int|Employee $approver): Builder
    {
        $approverId = $approver instanceof Employee ? $approver->id : $approver;

        return $query->where('status', OvertimeRequestStatus::Pending)
            ->whereHas('approvals', function ($q) use ($approverId) {
                $q->where('approver_employee_id', $approverId)
                    ->where('decision', OvertimeApprovalDecision::Pending)
                    ->whereColumn('approval_level', 'overtime_requests.current_approval_level');
            });
    }

    /**
     * Check if the request can be edited.
     */
    protected function canBeEdited(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeEdited()
        );
    }

    /**
     * Check if the request can be cancelled.
     */
    protected function canBeCancelled(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->status->canBeCancelled()
        );
    }

    /**
     * Get expected hours as a formatted string.
     */
    protected function expectedHoursFormatted(): Attribute
    {
        return Attribute::make(
            get: function () {
                $hours = intdiv($this->expected_minutes, 60);
                $mins = $this->expected_minutes % 60;

                return sprintf('%d:%02d', $hours, $mins);
            }
        );
    }

    /**
     * Generate a unique reference number.
     */
    public static function generateReferenceNumber(): string
    {
        $year = now()->year;
        $prefix = 'OT-'.$year.'-';

        $lastNumber = self::query()
            ->where('reference_number', 'like', $prefix.'%')
            ->orderByDesc('reference_number')
            ->value('reference_number');

        if ($lastNumber) {
            $lastSequence = (int) substr($lastNumber, strlen($prefix));
            $nextSequence = $lastSequence + 1;
        } else {
            $nextSequence = 1;
        }

        return $prefix.str_pad((string) $nextSequence, 5, '0', STR_PAD_LEFT);
    }
}
