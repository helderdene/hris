<?php

namespace App\Models;

use App\Enums\DocumentRequestStatus;
use App\Enums\DocumentRequestType;
use App\Enums\EmploymentStatus;
use App\Enums\OffboardingStepStatus;
use App\Notifications\OffboardingNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Tracks an employee's separation: status, exit interview, clearance, final pay and COE.
 */
class EmployeeOffboarding extends TenantModel
{
    /** @use HasFactory<\Database\Factories\EmployeeOffboardingFactory> */
    use HasFactory;

    /**
     * Clearance items every offboarding starts with.
     *
     * @var list<string>
     */
    public const DEFAULT_CLEARANCE_ITEMS = [
        'Company ID',
        'Laptop / Computer Equipment',
        'Access Cards / Keys',
        'Uniform',
        'Company Documents / Files',
        'Cash Advances / Loans',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_id',
        'separation_type',
        'filed_date',
        'last_working_date',
        'reason',
        'separated_at',
        'exit_interview_date',
        'exit_interviewer',
        'exit_interview_notes',
        'final_pay_status',
        'final_pay_amount',
        'final_pay_released_date',
        'coe_status',
        'coe_issued_date',
        'coe_document_request_id',
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
            'separation_type' => EmploymentStatus::class,
            'filed_date' => 'date:Y-m-d',
            'last_working_date' => 'date:Y-m-d',
            'separated_at' => 'datetime',
            'exit_interview_date' => 'date:Y-m-d',
            'final_pay_status' => OffboardingStepStatus::class,
            'final_pay_amount' => 'decimal:2',
            'final_pay_released_date' => 'date:Y-m-d',
            'coe_status' => OffboardingStepStatus::class,
            'coe_issued_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Start an offboarding for an employee with the default clearance checklist,
     * plus any equipment recorded as issued during onboarding.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function startFor(Employee $employee, array $attributes): self
    {
        return DB::transaction(function () use ($employee, $attributes) {
            $offboarding = self::create([
                ...$attributes,
                'employee_id' => $employee->id,
                'created_by' => auth()->id(),
            ]);

            $issuedEquipment = OnboardingChecklistItem::query()
                ->whereNotNull('equipment_details')
                ->whereHas('checklist', fn ($query) => $query->where('employee_id', $employee->id))
                ->get()
                ->map(fn (OnboardingChecklistItem $item) => [$item->name, implode(' ', array_filter([
                    $item->equipment_details['model'] ?? null,
                    $item->equipment_details['serial_number'] ?? null,
                    $item->equipment_details['asset_tag'] ?? null,
                ]))])
                ->filter(fn (array $equipment) => $equipment[1] !== '')
                ->map(fn (array $equipment) => implode(' ', $equipment));

            $offboarding->clearanceItems()->createMany(
                collect(self::DEFAULT_CLEARANCE_ITEMS)
                    ->merge($issuedEquipment)
                    ->map(fn (string $name) => ['name' => mb_substr($name, 0, 255)])
                    ->all()
            );

            return $offboarding;
        });
    }

    /**
     * Mirror a COE document request onto the employee's offboarding. A new request
     * is linked to the open offboarding; after that the request is the source of truth.
     */
    public static function syncCoeRequest(DocumentRequest $request): void
    {
        if ($request->document_type !== DocumentRequestType::Coe) {
            return;
        }

        $offboarding = self::query()->where('coe_document_request_id', $request->id)->first();

        if (! $offboarding && $request->wasRecentlyCreated) {
            $offboarding = $request->employee?->currentOffboarding();

            if ($offboarding?->coeDocumentRequest || ! $offboarding?->isVisibleToEmployee()) {
                return;
            }
        }

        if (! $offboarding) {
            return;
        }

        $status = match ($request->status) {
            DocumentRequestStatus::Pending => OffboardingStepStatus::Pending,
            DocumentRequestStatus::Processing => OffboardingStepStatus::Processing,
            DocumentRequestStatus::Ready, DocumentRequestStatus::Collected => OffboardingStepStatus::Completed,
        };

        $offboarding->update([
            'coe_document_request_id' => $request->id,
            'coe_status' => $status,
            'coe_issued_date' => $status === OffboardingStepStatus::Completed
                ? ($offboarding->coe_issued_date ?? now()->toDateString())
                : null,
        ]);
    }

    /**
     * Whether the employee may see this offboarding. A termination or other
     * HR-initiated separation stays hidden until it is finalised.
     */
    public function isVisibleToEmployee(): bool
    {
        return $this->separation_type === EmploymentStatus::Resigned || $this->separated_at !== null;
    }

    /**
     * Send the employee an in-app notification, if they may see this offboarding.
     */
    public function notifyEmployee(string $title, string $message): void
    {
        if ($this->isVisibleToEmployee()) {
            $this->employee->user?->notify(new OffboardingNotification($title, $message, '/my/resignation'));
        }
    }

    /**
     * Get the COE document request linked to this offboarding.
     */
    public function coeDocumentRequest(): BelongsTo
    {
        return $this->belongsTo(DocumentRequest::class, 'coe_document_request_id');
    }

    /**
     * Keep confidential exit interview notes out of the audit log.
     *
     * @return array<string>
     */
    protected function getAuditExcludedAttributes(): array
    {
        return ['exit_interview_notes'];
    }

    /**
     * Get the employee being offboarded.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the clearance checklist items.
     */
    public function clearanceItems(): HasMany
    {
        return $this->hasMany(OffboardingClearanceItem::class);
    }
}
