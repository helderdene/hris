<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for BIR Form 2316 certificates.
 *
 * Stores generated certificates of compensation payment and tax withheld
 * for employees, used for annual BIR compliance.
 */
class Bir2316Certificate extends TenantModel
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'bir_2316_certificates';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'tax_year',
        'compensation_data',
        'pdf_path',
        'generated_at',
        'generated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'compensation_data' => 'array',
            'generated_at' => 'datetime',
            'tax_year' => 'integer',
        ];
    }

    /**
     * Get the employee this certificate belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who generated this certificate.
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
