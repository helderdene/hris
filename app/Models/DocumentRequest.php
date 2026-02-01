<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use App\Enums\DocumentRequestType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Document request model for employee self-service document requests.
 *
 * Allows employees to request documents such as COE, employment
 * verification, ITR copies, and payslip copies.
 */
class DocumentRequest extends TenantModel
{
    /** @use HasFactory<\Database\Factories\DocumentRequestFactory> */
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'document_type',
        'status',
        'notes',
        'admin_notes',
        'processed_at',
        'collected_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentRequestType::class,
            'status' => DocumentRequestStatus::class,
            'processed_at' => 'datetime',
            'collected_at' => 'datetime',
        ];
    }

    /**
     * Get the employee that owns this document request.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee(Builder $query, int $employeeId): Builder
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by pending status.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', DocumentRequestStatus::Pending);
    }
}
