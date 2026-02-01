<?php

namespace App\Models;

use App\Enums\BankAccountType;
use App\Enums\PayType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * EmployeeCompensation model for storing employee salary and bank account information.
 *
 * Extends TenantModel for multi-tenant database isolation.
 * One-to-one relationship with Employee via employee_id foreign key.
 */
class EmployeeCompensation extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeCompensationFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'employee_compensations';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'basic_pay',
        'currency',
        'pay_type',
        'effective_date',
        'bank_name',
        'account_name',
        'account_number',
        'account_type',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'basic_pay' => 'decimal:2',
            'pay_type' => PayType::class,
            'account_type' => BankAccountType::class,
            'effective_date' => 'date',
        ];
    }

    /**
     * Get the employee this compensation belongs to.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
