# KasamaHR SaaS Tier Subscription Design

## Overview

This document outlines the design for transforming KasamaHR into a tiered SaaS product with subscription-based billing. Tiers are differentiated primarily by the number of modules included, with additional limits on employee count, users, and storage.

**Current state:** All tenants have unrestricted access to all 20 modules with no billing system.

**Target state:** 3 subscription tiers (Starter, Professional, Enterprise) with module-based gating, per-employee pricing, and PayMongo billing.

**Key design decision:** PayMongo has no Laravel Cashier equivalent and no native per-seat `quantity` parameter on subscriptions. We use **per-tenant dynamic plans** where each tenant gets a unique PayMongo plan with `amount = unit_price × employee_count`, updated via API when employee count changes.

---

## Part 1: Business Design

### 1.1 Module Inventory

KasamaHR consists of 21 distinct functional modules:

| # | Module | Description |
|---|--------|-------------|
| 1 | HR Management | Employee records, HR analytics dashboard, company documents, announcements, certifications, document requests |
| 2 | Organization Management | Departments, positions, salary grades, work locations, org chart, work schedules, holidays, certification types, competencies |
| 3 | Time & Attendance | Attendance logs, daily time records (DTR), device management |
| 4 | Leave Management | Leave applications, approvals, calendar, leave types, balances, adjustments |
| 5 | Payroll | Payroll entries, periods, adjustments, loans, government contributions (SSS, PhilHealth, Pag-IBIG, Tax), payslips |
| 6 | HR Compliance & Reporting | SSS, PhilHealth, Pag-IBIG, BIR reports, BIR 2316 certificates |
| 7 | Employee Self-Service | Personal dashboard, DTR, payslips, documents, loans, leaves, goals, evaluations, training, compliance, onboarding |
| 8 | User & Access Management | User CRUD, roles, invitations, profile settings, password management, 2FA |
| 9 | Recruitment | Job requisitions, postings, candidates, applications, interviews, offer templates, offers, recruitment analytics |
| 10 | Onboarding & Pre-boarding | Pre-boarding/onboarding checklists, templates, tasks, self-service |
| 11 | Training & Development | Course catalog, training sessions, calendar, enrollments, history, categories, materials |
| 12 | Performance Management | Performance cycles, goals, KPIs, 360-degree evaluations, competency evaluations, development plans, analytics |
| 13 | Probationary Management | Criteria templates, evaluations, approvals, status tracking |
| 14 | Manager/Supervisor Module | Team goals management, probationary evaluations, team compliance tracking, leave approvals |
| 15 | Help Center | Help articles, categories, search, admin CMS |
| 16 | Compliance Training | Compliance dashboard, courses, assignments/rules, reports, assessments, certificates |
| 17 | Biometric Integration | Biometric device management, employee sync, sync logs, MQTT commands |
| 18 | Background Check & Reference | Background checks, documents, reference checks |
| 19 | Audit & Security | Audit logs (model change tracking), password confirmation |
| 20 | Careers/Public Portal | Public careers page, job listings, public applications, offer response portal |
| 21 | HR Analytics | HR analytics dashboard, workforce metrics, turnover analysis |

### 1.2 Subscription Tiers

#### Starter — Core HR Operations (9 modules)

For small companies that need essential HR, payroll, and compliance tools.

| Module | Included Features |
|--------|-------------------|
| HR Management | Employee CRUD, company documents, announcements, document requests |
| Organization Management | Departments, positions, salary grades, work locations, org chart, work schedules, holidays |
| Time & Attendance | Attendance logs, daily time records |
| Leave Management | Leave applications, approvals, calendar, types, balances, adjustments |
| Payroll | Entries, periods, adjustments, loans, government contributions, calculator, payslips |
| HR Compliance & Reporting | SSS, PhilHealth, Pag-IBIG, BIR reports, BIR 2316 |
| Employee Self-Service (Basic) | My DTR, payslips, leave applications, loan applications, document requests, announcements, BIR 2316 |
| User & Access Management | User CRUD, roles, invitations, profile settings, 2FA |
| Biometric Integration | Device management, employee sync, MQTT attendance logging (up to 2 devices) |
| Web Kiosk Clock-In *(planned)* | Browser-based clock-in/out terminal for offices without biometric hardware |

> **Why Biometric is in Starter:** Biometric devices are currently the sole method of recording attendance. Without this module, Time & Attendance would have no data source. Tiers are differentiated by device count limits instead.
>
> **Planned — Web Kiosk Clock-In:** A software-only alternative for companies that don't have biometric hardware. Available on all tiers. See [Part 6: Planned Features](#part-6-planned-features--web-kiosk-clock-in) for details.

#### Professional — Strategic HR (Starter + 8 modules)

For growing companies that need recruitment, training, and performance tools.

Everything in Starter (with increased limits: up to 10 biometric devices), plus:

| Module | Included Features |
|--------|-------------------|
| Recruitment | Job requisitions, postings, candidates, applications, interviews, offer templates, offers, analytics |
| Onboarding & Pre-boarding | Checklists, templates, tasks, employee self-service |
| Training & Development | Course catalog, sessions, calendar, enrollments, history, categories, materials |
| Performance Management | Cycles, goals, KPIs, 360 evaluations, competency evaluations, development plans, analytics |
| Probationary Management | Criteria templates, evaluations, approvals, status view |
| Manager/Supervisor Module | Team goals, probationary evaluations, leave approvals |
| Help Center | Articles, categories, search, admin CMS |
| HR Analytics | Workforce metrics, turnover analysis, HR analytics dashboard |

Extended Self-Service additions: My goals, my evaluations, development plans, training sessions, enrollments, certifications, probationary status, my onboarding, my pre-boarding.

#### Enterprise — Full Platform (Professional + 4 modules)

For large organizations needing full compliance, integrations, and employer branding.

Everything in Professional (with unlimited biometric devices), plus:

| Module | Included Features |
|--------|-------------------|
| Compliance Training | Dashboard, courses, assignments/rules, reports, assessments, certificates |
| Background Check & Reference | Background checks, documents, reference checks |
| Audit & Security | Audit logs, password confirmation for sensitive actions |
| Careers/Public Portal | Public careers page, job listings, public applications, offer responses |

Extended Self-Service additions: My compliance training, compliance certificates, team compliance tracking.

### 1.3 Feature Comparison Matrix

| Capability | Starter | Professional | Enterprise | Custom |
|------------|:-------:|:------------:|:----------:|:------:|
| **Core HR** | | | | |
| Employee Records | ✓ | ✓ | ✓ | Pick & choose |
| Organization Management | ✓ | ✓ | ✓ | Pick & choose |
| Time & Attendance | ✓ | ✓ | ✓ | Pick & choose |
| Biometric Integration | ✓ | ✓ | ✓ | Pick & choose |
| Web Kiosk Clock-In *(planned)* | ✓ | ✓ | ✓ | Pick & choose |
| Leave Management | ✓ | ✓ | ✓ | Pick & choose |
| Payroll Processing | ✓ | ✓ | ✓ | Pick & choose |
| Government Compliance Reports | ✓ | ✓ | ✓ | Pick & choose |
| Employee Self-Service (Basic) | ✓ | ✓ | ✓ | Pick & choose |
| **Strategic HR** | | | | |
| Recruitment & Hiring | — | ✓ | ✓ | Pick & choose |
| Onboarding & Pre-boarding | — | ✓ | ✓ | Pick & choose |
| Training & Development | — | ✓ | ✓ | Pick & choose |
| Performance Management | — | ✓ | ✓ | Pick & choose |
| Probationary Management | — | ✓ | ✓ | Pick & choose |
| Manager/Supervisor Tools | — | ✓ | ✓ | Pick & choose |
| Help Center | — | ✓ | ✓ | Pick & choose |
| HR Analytics Dashboard | — | ✓ | ✓ | Pick & choose |
| **Enterprise** | | | | |
| Compliance Training | — | — | ✓ | Pick & choose |
| Background & Reference Checks | — | — | ✓ | Pick & choose |
| Audit Logs | — | — | ✓ | Pick & choose |
| Public Careers Portal | — | — | ✓ | Pick & choose |
| **Limits** | | | | |
| Max Employees | 50 | 250 | Unlimited | Negotiated |
| Max Admin/HR Users | 3 | 10 | Unlimited | Negotiated |
| Max Departments | 5 | Unlimited | Unlimited | Negotiated |
| Max Biometric Devices | 2 | 10 | Unlimited | Negotiated |
| Storage | 1 GB | 10 GB | 100 GB | Negotiated |
| **Extras** | | | | |
| API Access | — | Read-only | Full | Negotiated |
| Custom Branding | Logo only | Logo + Colors | Full White-Label | Negotiated |
| SSO / SAML | — | — | ✓ | Negotiated |
| Support | Email | Priority Email | Dedicated Account Manager | Dedicated AM |

### 1.4 Pricing Strategy

**Model:** Per-employee, per-month pricing with annual discount.

| Tier | Monthly (per employee) | Annual (per employee) | Savings |
|------|:----------------------:|:---------------------:|:-------:|
| Starter | ₱50/mo | ₱500/yr | ~17% |
| Professional | ₱100/mo | ₱1,000/yr | ~17% |
| Enterprise | ₱150/mo | ₱1,500/yr | ~17% |
| Custom | Negotiated | Negotiated | Per-deal |

**Minimums:**
- Starter: 5 employees minimum (₱250/mo minimum)
- Professional: 10 employees minimum (₱1,000/mo minimum)
- Enterprise: 25 employees minimum (₱3,750/mo minimum)

**PayMongo implementation:** Each tenant gets a unique PayMongo plan with `amount = price_per_unit × max(employee_count, tier_minimum)`. When employee count changes, the plan amount is updated via `PUT /v1/subscriptions/plans/{plan_id}`, and future invoices reflect the new amount.

### 1.5 Add-On Slots

Tenants can purchase additional capacity beyond their plan's included limits without upgrading to a higher tier. Add-ons are billed monthly on top of the base subscription.

#### Available Add-Ons

| Add-On | Unit | Price (per unit/mo) | Available On |
|--------|------|:-------------------:|:------------:|
| Extra Employee Slots | Pack of 10 employees | ₱25/mo | Starter, Professional |
| Extra Biometric Devices | 1 device | ₱50/mo | Starter, Professional |

> **Enterprise:** Does not need add-ons — employees and biometric devices are already unlimited.

#### How It Works

1. Tenant admin navigates to **Billing → Add-Ons** page
2. Selects the add-on type and quantity (e.g., 2 packs of 10 employees = 20 extra slots)
3. System creates a one-time PayMongo payment or adds the amount to the next billing cycle
4. On successful payment, the tenant's effective limit increases immediately
5. Add-ons renew automatically with the subscription each billing cycle
6. Add-ons can be removed at any time — takes effect on the next billing cycle

#### Effective Limit Calculation

```
effective_max_employees = plan.max_employees + (extra_employee_packs × 10)
effective_max_devices   = plan.max_biometric_devices + extra_device_count
```

**Example:** Starter plan (50 employees, 2 devices) + 3 employee packs + 1 extra device = 80 employees, 3 devices.

#### Downgrade Validation

When removing add-ons or downgrading plans, the system validates that current usage fits within the new effective limits. If not, the tenant is shown specific warnings (e.g., "You currently have 65 employees but the new limit would be 50. Please deactivate employees before removing this add-on.").

### 1.6 Custom Plan (Sales-Assisted)

For organizations that need specific modules at scale but don't fit neatly into Starter, Professional, or Enterprise — e.g., a production plant with 2,000 employees that only needs core HR, payroll, and attendance.

#### How It Works

1. Client contacts sales or requests a custom quote via the billing page
2. Sales team creates a **Custom plan** via the Platform Admin Dashboard
3. The plan includes a hand-picked set of modules and a negotiated per-employee rate
4. Employee limits, device limits, and other constraints are set per-deal
5. Billing is handled through PayMongo (same per-tenant dynamic plan) or via invoice for large contracts

#### Custom Plan Configuration (Admin Dashboard)

| Setting | Description |
|---------|-------------|
| Plan Name | Custom label (e.g., "Starter XL — Acme Manufacturing") |
| Modules | Cherry-picked from the full 21-module list |
| Per-Employee Rate | Negotiated price (e.g., ₱35/mo for high-volume Starter-only) |
| Employee Limit | Set per-deal (e.g., 5,000) or unlimited |
| Biometric Device Limit | Set per-deal or unlimited |
| Billing Method | PayMongo subscription or manual invoice |
| Contract Term | Monthly, annual, or custom (e.g., 2-year contract) |

#### Example Scenario

> **Acme Manufacturing** — 2,000 factory workers. They only need: HR Management, Organization Management, Time & Attendance, Biometric Integration, Leave Management, Payroll, HR Compliance, Employee Self-Service, User & Access Management (9 Starter modules).
>
> **Custom deal:** ₱35/employee/mo × 2,000 = **₱70,000/mo** (vs ₱100,000/mo on standard Starter, or ₱200,000/mo on Professional for modules they don't need).

#### Technical Implementation

- Custom plans use the same `plans` table with `is_custom = true` flag
- Modules are assigned via `plan_modules` pivot (same as standard plans)
- `limits` JSON is fully configurable per-plan
- Custom plans are **not shown** on the public pricing page — only assignable by super admins
- Platform Admin Dashboard gets a "Create Custom Plan" action (see Part 6)

#### Database Change

Add to `plans` table:

```php
$table->boolean('is_custom')->default(false);    // true for sales-assisted custom plans
$table->foreignId('tenant_id')->nullable();       // if set, plan is exclusive to this tenant
```

### 1.7 Trial Period

- **Duration:** 14 days
- **Tier:** Professional (gives users the strategic HR experience)
- **Credit card required:** No
- **Expiration behavior:** Auto-locks access, requiring plan selection to continue
- **Enterprise trial:** Available by request (sales-assisted, 30 days)

### 1.8 PayMongo Limitations

| Limitation | Impact |
|------------|--------|
| No proration | Plan changes take effect on the next billing cycle, not mid-cycle |
| 24-hour payment window | First subscription payment must complete within 24 hours or PayMongo auto-cancels |
| Cards + Maya only | Subscriptions only support card and Maya payment methods |
| No customer portal | Must build billing management UI in-app (no Stripe Portal equivalent) |
| No native per-seat billing | Must use per-tenant dynamic plans with manual amount updates |

---

## Part 2: Technical Implementation

### 2.1 Architecture Decisions

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Payment provider | PayMongo | Philippine-focused, PHP currency native, local payment methods |
| Billable entity | `Tenant` model | Billing is per-organization, not per-user |
| Billing DB | Platform database | Billing data is cross-tenant; must survive tenant DB operations |
| Module gating | Middleware + Inertia shared props | Matches existing `EnsureRole` pattern; gates both backend and frontend |
| Plan-module mapping | Database table (`plan_modules`) | Allows plan customization at runtime without code deploys |
| Employee billing | Per-tenant dynamic PayMongo plans | Amount = unit_price × employee_count, updated on employee changes |
| Trial tracking | `trial_ends_at` on tenant (not PayMongo) | Allows trial without payment method upfront |
| Data on downgrade | Preserved, access restricted | Never delete customer data; reduces churn friction |
| Webhook endpoint | Main domain (`/paymongo/webhook`) | Webhooks are application-wide, not tenant-specific |
| Frontend gating | `useSubscription` composable | Follows existing `useTenant` composable pattern |
| Connection pattern | `getConnectionName()` method | Matches existing `Tenant` model pattern (returns 'platform' for MySQL, null for SQLite/testing) |

### 2.2 PayMongo SDK & Configuration

#### Installation

```bash
composer require paymongo/paymongo-php
```

#### Environment Variables

```env
PAYMONGO_SECRET_KEY=sk_test_...
PAYMONGO_PUBLIC_KEY=pk_test_...
PAYMONGO_WEBHOOK_SECRET=whsec_...
```

#### Configuration Files

```php
// config/paymongo.php
return [
    'secret_key' => env('PAYMONGO_SECRET_KEY'),
    'public_key' => env('PAYMONGO_PUBLIC_KEY'),
    'webhook_secret' => env('PAYMONGO_WEBHOOK_SECRET'),
];
```

```php
// config/billing.php
return [
    'trial_days' => env('BILLING_TRIAL_DAYS', 14),
    'trial_plan' => env('BILLING_TRIAL_PLAN', 'professional'),
    'currency' => 'PHP',
    'minimum_employees' => [
        'starter' => 5,
        'professional' => 10,
        'enterprise' => 25,
    ],
];
```

### 2.3 Module Enum

A new enum to define canonical, gatable module identifiers. Distinct from the existing `Permission` enum which handles CRUD actions within modules.

```php
// app/Enums/Module.php
enum Module: string
{
    // Starter (always available on any paid plan)
    case HrManagement = 'hr_management';
    case OrganizationManagement = 'organization_management';
    case TimeAttendance = 'time_attendance';
    case BiometricIntegration = 'biometric_integration';
    case LeaveManagement = 'leave_management';
    case Payroll = 'payroll';
    case HrCompliance = 'hr_compliance';
    case EmployeeSelfService = 'employee_self_service';
    case UserAccessManagement = 'user_access_management';

    // Professional
    case Recruitment = 'recruitment';
    case OnboardingPreboarding = 'onboarding_preboarding';
    case TrainingDevelopment = 'training_development';
    case PerformanceManagement = 'performance_management';
    case ProbationaryManagement = 'probationary_management';
    case ManagerSupervisor = 'manager_supervisor';
    case HelpCenter = 'help_center';
    case HrAnalytics = 'hr_analytics';

    // Enterprise
    case ComplianceTraining = 'compliance_training';
    case BackgroundCheckReference = 'background_check_reference';
    case AuditSecurity = 'audit_security';
    case CareersPortal = 'careers_portal';

    public function label(): string { /* human-readable label */ }

    public static function starterModules(): array { /* 9 starter cases */ }
    public static function professionalModules(): array { /* starter + 8 professional cases */ }
    public static function enterpriseModules(): array { /* all 21 cases */ }
}
```

### 2.4 Subscription Status Enum

```php
// app/Enums/SubscriptionStatus.php
enum SubscriptionStatus: string
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Unpaid = 'unpaid';
    case Cancelled = 'cancelled';
    case Incomplete = 'incomplete';
    case IncompleteCancelled = 'incomplete_cancelled';

    public function label(): string { /* human-readable label */ }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
```

### 2.5 Add-On Type Enum

```php
// app/Enums/AddonType.php
enum AddonType: string
{
    case EmployeeSlots = 'employee_slots';
    case BiometricDevices = 'biometric_devices';

    public function label(): string { /* human-readable label */ }

    /** Number of units granted per quantity */
    public function unitsPerQuantity(): int
    {
        return match ($this) {
            self::EmployeeSlots => 10,
            self::BiometricDevices => 1,
        };
    }

    /** Default price per unit in centavos */
    public function defaultPrice(): int
    {
        return match ($this) {
            self::EmployeeSlots => 2500,      // ₱25/mo per pack of 10
            self::BiometricDevices => 5000,   // ₱50/mo per device
        };
    }
}
```

### 2.6 Database Schema

All billing tables live in the **platform database** alongside `tenants` and `users`.

#### `plans` table

```php
Schema::create('plans', function (Blueprint $table) {
    $table->id();
    $table->string('name');                    // "Starter", "Professional", "Enterprise"
    $table->string('slug')->unique();          // "starter", "professional", "enterprise"
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('is_custom')->default(false);  // true for sales-assisted custom plans
    $table->foreignId('tenant_id')->nullable()->constrained()->nullOnDelete(); // exclusive to this tenant if set
    $table->integer('sort_order')->default(0);
    $table->json('limits');                    // {"max_employees": 50, "max_users": 3, ...}
    $table->timestamps();
});
```

**`limits` JSON structure:**
```json
{
    "max_employees": 50,
    "max_admin_users": 3,
    "max_departments": 5,
    "max_biometric_devices": 2,
    "storage_gb": 1,
    "api_access": false
}
```

#### `plan_prices` table

```php
Schema::create('plan_prices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
    $table->string('billing_interval');        // "monthly" or "yearly"
    $table->integer('price_per_unit');          // in centavos (₱99 = 9900)
    $table->string('currency')->default('PHP');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### `plan_modules` table (pivot)

```php
Schema::create('plan_modules', function (Blueprint $table) {
    $table->id();
    $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
    $table->string('module');                  // Module enum value
    $table->timestamps();

    $table->unique(['plan_id', 'module']);
});
```

#### `subscriptions` table

```php
Schema::create('subscriptions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('name')->default('default');
    $table->string('paymongo_id')->unique()->nullable();
    $table->string('paymongo_plan_id')->nullable();
    $table->string('paymongo_status')->nullable();
    $table->foreignId('plan_price_id')->nullable()->constrained()->nullOnDelete();
    $table->integer('quantity')->default(1);
    $table->timestamp('current_period_end')->nullable();
    $table->timestamp('ends_at')->nullable();
    $table->timestamps();

    $table->index(['tenant_id', 'paymongo_status']);
});
```

#### `tenant_addons` table

```php
Schema::create('tenant_addons', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('type');                    // "employee_slots", "biometric_devices"
    $table->integer('quantity')->default(1);   // number of units purchased
    $table->integer('price_per_unit');         // in centavos per unit/mo
    $table->string('currency')->default('PHP');
    $table->boolean('is_active')->default(true);
    $table->timestamp('expires_at')->nullable(); // null = renews with subscription
    $table->timestamps();

    $table->index(['tenant_id', 'type', 'is_active']);
});
```

**`type` values and their units:**
| Type | Unit per Quantity | Effect |
|------|-------------------|--------|
| `employee_slots` | 10 employees | Adds `quantity × 10` to max_employees |
| `biometric_devices` | 1 device | Adds `quantity` to max_biometric_devices |

#### Additions to `tenants` table

```php
Schema::table('tenants', function (Blueprint $table) {
    $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
    $table->string('paymongo_customer_id')->nullable()->unique();
    $table->timestamp('trial_ends_at')->nullable();
    $table->integer('employee_count_cache')->default(0);
});
```

### 2.7 New Models

#### `Plan` model

```php
// app/Models/Plan.php
class Plan extends Model
{
    // Uses getConnectionName() pattern from Tenant model

    protected $fillable = ['name', 'slug', 'description', 'is_active', 'is_custom', 'tenant_id', 'sort_order', 'limits'];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
        ];
    }

    public function prices(): HasMany { /* ... */ }
    public function modules(): HasMany { /* ... */ }
    public function tenants(): HasMany { /* ... */ }

    public function hasModule(Module $module): bool
    {
        return $this->modules->contains('module', $module->value);
    }

    public function getLimit(string $key, mixed $default = null): mixed
    {
        return data_get($this->limits, $key, $default);
    }
}
```

#### `PlanPrice` model

```php
// app/Models/PlanPrice.php
class PlanPrice extends Model
{
    protected $fillable = ['plan_id', 'billing_interval', 'price_per_unit', 'currency', 'is_active'];

    public function plan(): BelongsTo { /* ... */ }
}
```

#### `PlanModule` model

```php
// app/Models/PlanModule.php
class PlanModule extends Model
{
    protected $fillable = ['plan_id', 'module'];

    public function plan(): BelongsTo { /* ... */ }
}
```

#### `Subscription` model

```php
// app/Models/Subscription.php
class Subscription extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'paymongo_id', 'paymongo_plan_id',
        'paymongo_status', 'plan_price_id', 'quantity',
        'current_period_end', 'ends_at',
    ];

    protected function casts(): array
    {
        return [
            'paymongo_status' => SubscriptionStatus::class,
            'current_period_end' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { /* ... */ }
    public function planPrice(): BelongsTo { /* ... */ }

    public function active(): bool
    {
        return $this->paymongo_status === SubscriptionStatus::Active
            && ($this->ends_at === null || $this->ends_at->isFuture());
    }

    public function cancelled(): bool
    {
        return $this->ends_at !== null;
    }

    public function onGracePeriod(): bool
    {
        return $this->cancelled() && $this->ends_at->isFuture();
    }
}
```

#### `TenantAddon` model

```php
// app/Models/TenantAddon.php
class TenantAddon extends Model
{
    // Uses getConnectionName() pattern from Tenant model

    protected $fillable = [
        'tenant_id', 'type', 'quantity', 'price_per_unit',
        'currency', 'is_active', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AddonType::class,
            'is_active' => 'boolean',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo { /* ... */ }

    /** Total extra units this add-on provides */
    public function extraUnits(): int
    {
        return $this->quantity * $this->type->unitsPerQuantity();
    }

    /** Monthly cost of this add-on in centavos */
    public function monthlyCost(): int
    {
        return $this->quantity * $this->price_per_unit;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
```

### 2.8 Tenant Model Changes

Add to `app/Models/Tenant.php`:

```php
class Tenant extends Model
{
    // Add to $fillable:
    // 'plan_id', 'paymongo_customer_id', 'trial_ends_at', 'employee_count_cache'

    protected function casts(): array
    {
        return [
            // ... existing casts
            'trial_ends_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function subscription(string $name = 'default'): ?Subscription
    {
        return $this->subscriptions()->where('name', $name)->first();
    }

    public function subscribed(string $name = 'default'): bool
    {
        $subscription = $this->subscription($name);

        return $subscription !== null && $subscription->active();
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isFuture();
    }

    public function trialExpired(): bool
    {
        return $this->trial_ends_at !== null && $this->trial_ends_at->isPast();
    }

    public function hasActiveAccess(): bool
    {
        return $this->onTrial() || $this->subscribed('default');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(TenantAddon::class);
    }

    public function activeAddons(): HasMany
    {
        return $this->addons()->active();
    }

    public function hasModule(Module $module): bool
    {
        if (! $this->plan) {
            return false;
        }

        return $this->plan->hasModule($module);
    }

    public function availableModules(): array
    {
        if (! $this->plan) {
            return [];
        }

        return $this->plan->modules->pluck('module')->toArray();
    }

    /** Get effective limit including add-ons */
    public function effectiveLimit(string $key): int|null
    {
        $base = $this->plan?->getLimit($key);

        if ($base === null || $base === -1) {
            return $base; // null = no plan, -1 = unlimited
        }

        $addonType = match ($key) {
            'max_employees' => AddonType::EmployeeSlots,
            'max_biometric_devices' => AddonType::BiometricDevices,
            default => null,
        };

        if (! $addonType) {
            return $base;
        }

        $extra = $this->activeAddons()
            ->where('type', $addonType->value)
            ->get()
            ->sum(fn (TenantAddon $addon) => $addon->extraUnits());

        return $base + $extra;
    }
}
```

### 2.9 PayMongo Service Layer

#### Service Provider

```php
// app/Providers/PayMongoServiceProvider.php
// Registers a singleton PaymongoClient using config('paymongo.secret_key')
```

#### PayMongoService (Main Facade)

```php
// app/Services/Billing/PayMongoService.php
class PayMongoService
{
    public function __construct(
        public PayMongoCustomerService $customers,
        public PayMongoSubscriptionService $subscriptions,
        public PayMongoWebhookService $webhooks,
    ) {}
}
```

#### PayMongoCustomerService

```php
// app/Services/Billing/PayMongoCustomerService.php
// - createOrGet(Tenant): Creates PayMongo customer, stores paymongo_customer_id on tenant
// - update(Tenant, attributes): Updates customer metadata
```

#### PayMongoSubscriptionService

```php
// app/Services/Billing/PayMongoSubscriptionService.php
// - create(Tenant, PlanPrice, quantity): Creates per-tenant PayMongo plan + subscription
// - updateQuantity(Subscription, newQuantity): Updates plan amount via PUT /v1/subscriptions/plans/{id}
// - changePlan(Subscription, PlanPrice): Switches to new plan via PUT /v1/subscriptions/{id}/plan
// - cancel(Subscription): Cancels subscription, updates local status
// - getCheckoutUrl(Tenant, PlanPrice, quantity): Creates PayMongo checkout session
```

#### PayMongoWebhookService

```php
// app/Services/Billing/PayMongoWebhookService.php
// - validateSignature(Request): Verifies HMAC webhook signature
// - handleEvent(payload): Routes to handler by event type
```

### 2.10 Per-Tenant Dynamic Plan Strategy

**On subscription creation:**
1. Create PayMongo plan: `POST /v1/subscriptions/plans` with `amount = price_per_unit × max(employee_count, tier_minimum)`, name = `"{tier}_{tenant_slug}_{interval}"`
2. Create subscription: `POST /v1/subscriptions` linking customer to this plan
3. Store `paymongo_plan_id` on local `subscriptions` table

**On employee count change:**
1. Calculate new amount: `(price_per_unit × max(new_count, tier_minimum)) + total_addon_cost`
2. Update plan: `PUT /v1/subscriptions/plans/{plan_id}` with new amount
3. Future invoices reflect the updated amount automatically

**On add-on purchase/update/cancel:**
1. Create or update `TenantAddon` record locally
2. Recalculate total plan amount: `(price_per_unit × max(employee_count, tier_minimum)) + sum(addon.quantity × addon.price_per_unit)`
3. Update PayMongo plan: `PUT /v1/subscriptions/plans/{plan_id}` with new total amount
4. Effective limits update immediately; billing change reflects on next invoice

### 2.11 Feature Gate Service

Centralized service for all module and limit access checks.

```php
// app/Services/FeatureGateService.php
class FeatureGateService
{
    public function __construct(
        private ?Tenant $tenant = null,
    ) {
        $this->tenant = $tenant ?? app('currentTenant');
    }

    public function hasModule(Module $module): bool { /* ... */ }
    public function hasAnyModule(Module ...$modules): bool { /* ... */ }
    public function availableModules(): array { /* ... */ }
    public function isWithinEmployeeLimit(): bool { /* uses tenant->effectiveLimit('max_employees') */ }
    public function isWithinUserLimit(): bool { /* ... */ }
    public function isWithinDeviceLimit(): bool { /* uses tenant->effectiveLimit('max_biometric_devices') */ }
    public function getLimit(string $key, mixed $default = null): mixed { /* ... */ }
    public function getEffectiveLimit(string $key): int|null { /* delegates to tenant->effectiveLimit() */ }
    public function activeAddons(): Collection { /* returns tenant's active add-ons */ }
    public function addonCostBreakdown(): array { /* returns per-addon cost summary for billing UI */ }
}
```

### 2.12 Module Gating Middleware

```php
// app/Http/Middleware/EnsureModuleAccess.php
class EnsureModuleAccess
{
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        // Non-tenant routes pass through
        // Super admins bypass module checks
        // Check each module via FeatureGateService
        // Return 403 JSON or redirect to upgrade page
    }
}
```

```php
// app/Http/Middleware/EnsureActiveSubscription.php
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        // Non-tenant routes pass through
        // Super admins bypass
        // Check tenant->hasActiveAccess()
        // Allow billing routes through
        // Return 403 JSON or redirect to billing page
    }
}
```

Register in `bootstrap/app.php`:

```php
$middleware->alias([
    'ensure-role' => EnsureRole::class,
    'module' => EnsureModuleAccess::class,
    'subscribed' => EnsureActiveSubscription::class,
]);

$middleware->appendToGroup('tenant', [
    EnsureActiveSubscription::class,
]);
```

### 2.13 Route Integration

Apply `module` middleware to route groups in `routes/tenant/`. Starter-tier modules do NOT get the middleware since they are always available on any paid plan.

**Modules requiring `module` middleware:**

| Module Enum Value | Route Prefix/File |
|-------------------|--------------------|
| `recruitment` | `routes/tenant/web-recruitment.php`, `routes/tenant/api-recruitment.php` |
| `onboarding_preboarding` | Onboarding route groups |
| `training_development` | Training route groups, `routes/tenant/api-training.php` |
| `performance_management` | Performance route groups, `routes/tenant/api-performance.php` |
| `probationary_management` | Probationary route groups |
| `manager_supervisor` | Manager-specific route groups |
| `help_center` | Help center route groups |
| `hr_analytics` | HR analytics route groups |
| `compliance_training` | Compliance route groups |
| `background_check_reference` | Background check route groups |
| `audit_security` | Audit log route groups |
| `careers_portal` | Careers/public portal route groups |

### 2.14 Frontend Gating

#### Shared Props via Inertia

Extend `HandleInertiaRequests` middleware to share subscription data:

```php
// In getTenantContext() method of app/Http/Middleware/HandleInertiaRequests.php
return [
    // ... existing props (id, name, slug, logo_url, primary_color, user_role, can_manage_*)
    'plan' => [
        'name' => $tenant->plan?->name,
        'slug' => $tenant->plan?->slug,
    ],
    'subscription' => [
        'status' => $this->getSubscriptionStatus($tenant),
        'is_on_trial' => $tenant->onTrial(),
        'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
        'is_subscribed' => $tenant->subscribed('default'),
    ],
    'available_modules' => app(FeatureGateService::class)->availableModules(),
    'effective_limits' => [
        'max_employees' => $tenant->effectiveLimit('max_employees'),
        'max_biometric_devices' => $tenant->effectiveLimit('max_biometric_devices'),
    ],
    'active_addons' => $tenant->activeAddons->map(fn ($addon) => [
        'id' => $addon->id,
        'type' => $addon->type->value,
        'quantity' => $addon->quantity,
        'extra_units' => $addon->extraUnits(),
    ]),
];
```

#### Vue Composable

```typescript
// resources/js/composables/useSubscription.ts
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export function useSubscription() {
    const page = usePage()
    const tenant = computed(() => page.props.tenant)

    const availableModules = computed<string[]>(
        () => tenant.value?.available_modules ?? []
    )

    const hasModule = (module: string): boolean =>
        availableModules.value.includes(module)

    const isSubscribed = computed(
        () => tenant.value?.subscription?.is_subscribed ?? false
    )

    const isOnTrial = computed(
        () => tenant.value?.subscription?.is_on_trial ?? false
    )

    const planName = computed(() => tenant.value?.plan?.name ?? null)
    const planSlug = computed(() => tenant.value?.plan?.slug ?? null)
    const trialEndsAt = computed(() => tenant.value?.subscription?.trial_ends_at ?? null)

    return {
        availableModules, hasModule, isSubscribed, isOnTrial,
        planName, planSlug, trialEndsAt,
    }
}
```

#### Sidebar Gating

In `TenantSidebar.vue`, wrap module sections with `hasModule()` checks:

```vue
<script setup>
import { useSubscription } from '@/composables/useSubscription'
const { hasModule } = useSubscription()
</script>

<template>
    <!-- Starter modules: always visible (no hasModule check needed) -->
    <SidebarSection title="HR Management">...</SidebarSection>
    <SidebarSection title="Time & Attendance">...</SidebarSection>
    <SidebarSection title="Leave Management">...</SidebarSection>
    <SidebarSection title="Payroll">...</SidebarSection>

    <!-- Professional modules: gated -->
    <SidebarSection v-if="hasModule('recruitment')" title="Recruitment">...</SidebarSection>
    <SidebarSection v-if="hasModule('training_development')" title="Training">...</SidebarSection>
    <SidebarSection v-if="hasModule('performance_management')" title="Performance">...</SidebarSection>

    <!-- Enterprise modules: gated -->
    <SidebarSection v-if="hasModule('compliance_training')" title="Compliance Training">...</SidebarSection>
    <SidebarSection v-if="hasModule('audit_security')" title="Audit & Security">...</SidebarSection>
</template>
```

### 2.15 Billing Routes & Controller

```php
// routes/tenant/web-billing.php (included from routes/tenant.php)
Route::prefix('billing')
    ->middleware('ensure-role:admin')
    ->group(function () {
        Route::get('/', [BillingController::class, 'index'])->name('tenant.billing.index');
        Route::get('/plans', [BillingController::class, 'plans'])->name('tenant.billing.plans');
        Route::get('/upgrade', [BillingController::class, 'upgrade'])->name('tenant.billing.upgrade');
        Route::post('/subscribe/{planPrice}', [BillingController::class, 'subscribe'])->name('tenant.billing.subscribe');
        Route::post('/change-plan/{planPrice}', [BillingController::class, 'changePlan'])->name('tenant.billing.change-plan');
        Route::post('/cancel', [BillingController::class, 'cancel'])->name('tenant.billing.cancel');
        Route::get('/invoices', [BillingController::class, 'invoices'])->name('tenant.billing.invoices');
        Route::get('/success', [BillingController::class, 'success'])->name('tenant.billing.success');

        // Add-Ons
        Route::get('/addons', [BillingController::class, 'addons'])->name('tenant.billing.addons');
        Route::post('/addons/purchase', [BillingController::class, 'purchaseAddon'])->name('tenant.billing.addons.purchase');
        Route::post('/addons/{tenantAddon}/update', [BillingController::class, 'updateAddon'])->name('tenant.billing.addons.update');
        Route::post('/addons/{tenantAddon}/cancel', [BillingController::class, 'cancelAddon'])->name('tenant.billing.addons.cancel');
    });
```

#### Controller Actions

| Action | Description |
|--------|-------------|
| `index` | Billing dashboard: current plan, usage stats, subscription status, invoices |
| `plans` | Plan comparison page with pricing toggle (monthly/yearly) |
| `upgrade` | Upgrade prompt shown when accessing locked modules |
| `subscribe` | Ensures PayMongo customer, creates checkout URL, redirects to PayMongo checkout |
| `changePlan` | Validates limits for downgrades, calls `changePlan()` on service |
| `cancel` | Calls cancel, redirects back with confirmation |
| `invoices` | List all invoices |
| `success` | Post-checkout confirmation callback |
| `addons` | Add-ons management page: current add-ons, available add-ons, usage vs limits |
| `purchaseAddon` | Validates tier eligibility (not Enterprise), creates `TenantAddon`, updates PayMongo plan amount |
| `updateAddon` | Changes add-on quantity (increase/decrease), validates usage before decrease |
| `cancelAddon` | Validates current usage fits within reduced limits, deactivates add-on on next billing cycle |

### 2.16 PayMongo Webhook Handling

Webhooks are received on the **main domain**, not tenant subdomains.

```php
// routes/platform.php
Route::post('/paymongo/webhook', [PayMongoWebhookController::class, 'handle'])
    ->withoutMiddleware(VerifyCsrfToken::class);
```

```php
// app/Http/Controllers/PayMongoWebhookController.php
// Handles:
// - subscription.activated → Sync local status to Active
// - subscription.past_due → Update status, notify admins
// - subscription.unpaid → Update status, notify admins
// - subscription.updated → Sync any plan/status changes
// - subscription.invoice.paid → Update current_period_end
// - subscription.invoice.payment_failed → Notify admins
```

### 2.17 Tenant Registration Changes

Modify `TenantRegistrationController::store()` to assign a trial:

```php
// After Tenant::create()
$professionalPlan = Plan::where('slug', 'professional')->first();

$tenant->update([
    'plan_id' => $professionalPlan->id,
    'trial_ends_at' => now()->addDays(config('billing.trial_days', 14)),
]);

// Create PayMongo customer for later subscription
app(PayMongoCustomerService::class)->createOrGet($tenant);
```

### 2.18 Upgrade/Downgrade Flow

#### Upgrade

1. Tenant admin navigates to billing plans page
2. Selects new (higher) plan
3. If no active subscription → redirect to PayMongo checkout session
4. If active subscription → call `PayMongoSubscriptionService::changePlan()`
5. New per-tenant PayMongo plan created for the new tier
6. Webhook confirms activation, updates local `plan_id`
7. New modules immediately available (sidebar re-renders on next page load)

#### Downgrade

1. Tenant admin selects lower plan
2. System validates limits:
   - Employee count within new plan's `max_employees`
   - Admin user count within new plan's `max_admin_users`
   - Storage usage within new plan's `storage_gb`
3. If over limits → show specific warnings with action items
4. If within limits → call `changePlan()` (takes effect next billing cycle)
5. Downgraded modules remain accessible until current billing period ends
6. At period end, webhook triggers plan change and modules become restricted

### 2.19 Employee Count Synchronization

```php
// app/Jobs/UpdateBillingQuantity.php (ShouldQueue)
class UpdateBillingQuantity implements ShouldQueue
{
    public function __construct(public Tenant $tenant) {}

    public function handle(): void
    {
        // Switch to tenant database to count employees
        // Count active employees
        // Update employee_count_cache on tenant
        // Call PayMongoSubscriptionService::updateQuantity() to update plan amount
    }
}
```

**Dispatch triggers:**
- Employee created (`EmployeeObserver::created`)
- Employee status changed (`EmployeeObserver::updated`)
- Employee deleted (`EmployeeObserver::deleted`)
- Register observer in `AppServiceProvider::boot()`

**Safety net:** Nightly scheduled command `SyncBillingQuantities` iterates all tenants with active subscriptions and dispatches `UpdateBillingQuantity`.

### 2.20 Limit Enforcement

All limit checks use `effectiveLimit()` which includes add-on capacity. When a limit is reached, the error message offers both upgrading and purchasing add-ons as options.

#### Employee Limit

In `EmployeeController::store()`:

```php
$gate = app(FeatureGateService::class);

if (! $gate->isWithinEmployeeLimit()) {
    return back()->withErrors([
        'limit' => "You've reached your employee limit ({$gate->getEffectiveLimit('max_employees')}). "
            . "You can purchase additional employee slots or upgrade your plan."
    ]);
}
```

#### User Limit

In user invitation and creation flows:

```php
if (! $gate->isWithinUserLimit()) {
    return back()->withErrors([
        'limit' => "You've reached your plan's admin/HR user limit. Please upgrade your plan."
    ]);
}
```

#### Biometric Device Limit

In biometric device registration/creation:

```php
if (! $gate->isWithinDeviceLimit()) {
    return back()->withErrors([
        'limit' => "You've reached your biometric device limit ({$gate->getEffectiveLimit('max_biometric_devices')}). "
            . "You can purchase additional device slots or upgrade your plan."
    ]);
}
```

### 2.21 Trial Expiration Handling

```php
// app/Console/Commands/CheckExpiredTrials.php
// Scheduled daily via routes/console.php
// Finds tenants with expired trials and no active subscription
// Notifies all admin users via TrialExpiredNotification
```

### 2.22 Notifications

| Notification | Trigger | Recipients |
|-------------|---------|------------|
| `TrialExpiredNotification` | Trial period ends with no subscription | Tenant admin users |
| `PaymentFailedNotification` | PayMongo invoice payment fails | Tenant admin users |
| `SubscriptionCancelledNotification` | Subscription cancelled | Tenant admin users |

### 2.23 Plan Seeder

```php
// database/seeders/PlanSeeder.php
// Seeds 3 plans (Starter, Professional, Enterprise)
// Each plan gets 2 prices (monthly, yearly) with price_per_unit in centavos
// Each plan gets its module assignments via plan_modules
// PayMongo plan IDs are NOT seeded (created dynamically per-tenant)
```

---

## Part 3: Implementation Roadmap

### Phase 1: Foundation (Week 1–2)

- [ ] Install PayMongo PHP SDK (`composer require paymongo/paymongo-php`)
- [ ] Create `Module` enum (`app/Enums/Module.php`)
- [ ] Create `SubscriptionStatus` enum (`app/Enums/SubscriptionStatus.php`)
- [ ] Create `config/billing.php` and `config/paymongo.php`
- [ ] Create platform migration for `plans`, `plan_prices`, `plan_modules`, `subscriptions`
- [ ] Create migration to add billing columns to `tenants` table
- [ ] Create `Plan`, `PlanPrice`, `PlanModule`, `Subscription`, `TenantAddon` models
- [ ] Add plan/subscription relationships and trial methods to `Tenant` model
- [ ] Create `PlanSeeder`
- [ ] Create model factories (`PlanFactory`, `SubscriptionFactory`)

### Phase 2: PayMongo Service Layer (Week 2–3)

- [ ] Create `PayMongoServiceProvider` (singleton client)
- [ ] Create `PayMongoCustomerService` (create/get/update customers)
- [ ] Create `PayMongoSubscriptionService` (create/update/cancel subscriptions, per-tenant plans)
- [ ] Create `PayMongoWebhookService` (signature validation, event routing)
- [ ] Create `PayMongoService` facade class

### Phase 3: Feature Gating (Week 3–4)

- [ ] Create `FeatureGateService`
- [ ] Create `EnsureModuleAccess` middleware
- [ ] Create `EnsureActiveSubscription` middleware
- [ ] Register middlewares in `bootstrap/app.php`
- [ ] Apply `module` middleware to all Professional and Enterprise route groups
- [ ] Add `subscribed` middleware to tenant middleware group
- [ ] Extend `HandleInertiaRequests` with subscription/module shared props
- [ ] Create `useSubscription` composable (`resources/js/composables/useSubscription.ts`)
- [ ] Update `TenantSidebar.vue` with `hasModule()` visibility checks

### Phase 4: Billing UI & Routes (Week 4–5)

- [ ] Create `BillingController` with Inertia pages
- [ ] Create billing Inertia pages: Index, Plans, Upgrade, Addons, Success
- [ ] Create `PayMongoWebhookController`
- [ ] Add billing routes (`routes/tenant/web-billing.php`)
- [ ] Add webhook route (`routes/platform.php`)
- [ ] Modify `TenantRegistrationController` for trial provisioning + PayMongo customer

### Phase 5: Employee Sync, Limits, Notifications (Week 5–6)

- [ ] Create `UpdateBillingQuantity` job
- [ ] Create `EmployeeObserver` to dispatch billing sync
- [ ] Create `SyncBillingQuantities` nightly command
- [ ] Create `CheckExpiredTrials` daily command
- [ ] Schedule commands in `routes/console.php`
- [ ] Add employee limit check to `EmployeeController::store()`
- [ ] Add user limit check to invitation/user creation flows
- [ ] Add device limit check to biometric device creation
- [ ] Integrate add-on costs into `UpdateBillingQuantity` PayMongo plan amount calculation
- [ ] Create notification classes (TrialExpired, PaymentFailed, SubscriptionCancelled)

### Phase 6: Platform Admin Dashboard (Week 6–7)

- [ ] Create `PlatformAdminController` with dashboard, tenant list, tenant detail actions
- [ ] Create admin layout (`resources/js/layouts/admin/Layout.vue`)
- [ ] Create `Admin/Dashboard.vue` with aggregate stats
- [ ] Create `Admin/Tenants/Index.vue` with search/filter/sort
- [ ] Create `Admin/Tenants/Show.vue` with subscription actions
- [ ] Add `/admin/*` routes to `routes/platform.php`
- [ ] Implement extend trial, assign plan, cancel subscription actions
- [ ] Create custom plan CRUD (Create, Edit pages + controller actions)
- [ ] Implement impersonation redirect (secure token pattern)

### Phase 7: Testing (Week 7–8)

- [ ] Feature tests for `FeatureGateService` (module access, limits, effective limits with add-ons)
- [ ] Feature tests for `EnsureModuleAccess` middleware
- [ ] Feature tests for `EnsureActiveSubscription` middleware
- [ ] Feature tests for `BillingController` (including add-on purchase, update, cancel)
- [ ] Feature tests for `TenantAddon` (effective limits, addon cost calculation, validation on removal)
- [ ] Feature tests for webhook handling
- [ ] Feature tests for employee count sync
- [ ] Feature tests for trial status and expiration
- [ ] Feature tests for `PlanSeeder`
- [ ] Feature tests for `PlatformAdminController` (dashboard stats, tenant management, subscription overrides)

---

## Part 4: Files to Create

| File | Purpose |
|------|---------|
| `app/Enums/AddonType.php` | Add-on type identifiers (employee_slots, biometric_devices) |
| `app/Enums/Module.php` | 21 module identifiers |
| `app/Enums/SubscriptionStatus.php` | PayMongo subscription statuses |
| `app/Models/Plan.php` | Subscription plan model |
| `app/Models/PlanPrice.php` | Plan price model |
| `app/Models/PlanModule.php` | Plan-to-module pivot |
| `app/Models/Subscription.php` | Local subscription record |
| `app/Models/TenantAddon.php` | Tenant add-on slots model |
| `app/Services/FeatureGateService.php` | Centralized module/limit checks |
| `app/Services/Billing/PayMongoService.php` | Main billing facade |
| `app/Services/Billing/PayMongoCustomerService.php` | Customer CRUD |
| `app/Services/Billing/PayMongoSubscriptionService.php` | Subscription lifecycle |
| `app/Services/Billing/PayMongoWebhookService.php` | Webhook validation/routing |
| `app/Providers/PayMongoServiceProvider.php` | PayMongo client singleton |
| `app/Http/Middleware/EnsureModuleAccess.php` | Route-level module gate |
| `app/Http/Middleware/EnsureActiveSubscription.php` | Active subscription gate |
| `app/Http/Controllers/BillingController.php` | Billing management UI |
| `app/Http/Controllers/PayMongoWebhookController.php` | Webhook handler |
| `app/Jobs/UpdateBillingQuantity.php` | Sync employee count to PayMongo |
| `app/Console/Commands/SyncBillingQuantities.php` | Nightly billing quantity sync |
| `app/Console/Commands/CheckExpiredTrials.php` | Trial expiration handler |
| `app/Observers/EmployeeObserver.php` | Trigger billing sync on employee changes |
| `app/Notifications/TrialExpiredNotification.php` | Trial expiry notice |
| `app/Notifications/PaymentFailedNotification.php` | Failed payment notice |
| `app/Notifications/SubscriptionCancelledNotification.php` | Cancellation notice |
| `config/billing.php` | Billing configuration |
| `config/paymongo.php` | PayMongo credentials |
| `database/migrations/xxxx_create_billing_tables.php` | All billing schema (incl. tenant_addons) |
| `database/factories/TenantAddonFactory.php` | TenantAddon test factory |
| `database/seeders/PlanSeeder.php` | Seed default plans with modules |
| `database/factories/PlanFactory.php` | Plan test factory |
| `database/factories/SubscriptionFactory.php` | Subscription test factory |
| `resources/js/composables/useSubscription.ts` | Frontend subscription composable |
| `resources/js/pages/Billing/Index.vue` | Billing dashboard page |
| `resources/js/pages/Billing/Plans.vue` | Plan comparison page |
| `resources/js/pages/Billing/Upgrade.vue` | Upgrade prompt page |
| `resources/js/pages/Billing/Addons.vue` | Add-ons management page |
| `resources/js/pages/Billing/Success.vue` | Post-checkout confirmation |
| `routes/tenant/web-billing.php` | Billing routes |
| `app/Http/Controllers/PlatformAdminController.php` | Platform admin dashboard + tenant management |
| `resources/js/pages/Admin/Dashboard.vue` | Platform overview stats |
| `resources/js/pages/Admin/Tenants/Index.vue` | Searchable tenant list |
| `resources/js/pages/Admin/Tenants/Show.vue` | Tenant detail + subscription actions |
| `resources/js/pages/Admin/Plans/Create.vue` | Custom plan creation form |
| `resources/js/pages/Admin/Plans/Edit.vue` | Custom plan edit form |
| `resources/js/layouts/admin/Layout.vue` | Admin sidebar layout |

## Part 5: Files to Modify

| File | Change |
|------|--------|
| `app/Models/Tenant.php` | Add plan/subscription relationships, trial methods, PayMongo fields |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share plan/subscription/modules in tenant context |
| `resources/js/components/TenantSidebar.vue` | Add `hasModule()` visibility checks |
| `bootstrap/app.php` | Register `module` and `subscribed` middleware aliases, add to tenant group |
| `app/Http/Controllers/TenantRegistrationController.php` | Assign trial plan + create PayMongo customer |
| `app/Http/Controllers/EmployeeController.php` | Add employee limit check in store() |
| `app/Providers/AppServiceProvider.php` | Register EmployeeObserver |
| `routes/tenant.php` | Include billing routes file |
| `routes/platform.php` | Add webhook route + `/admin/*` route group |
| `routes/tenant/web-recruitment.php` | Add `module:recruitment` middleware |
| `routes/tenant/web-modules.php` | Add module middleware to gated sections |
| `routes/tenant/api-recruitment.php` | Add module middleware |
| `routes/tenant/api-training.php` | Add module middleware |
| `routes/tenant/api-performance.php` | Add module middleware |
| `routes/console.php` | Schedule billing commands |

---

## Part 6: Platform Admin Dashboard

### Overview

A centralized dashboard on the main platform domain for super admins to manage all tenants, subscriptions, and billing operations. Currently the only super admin feature is help center content management — this adds full tenant lifecycle management.

**Access:** Super admins only, via `Gate::authorize('super-admin')` (matches existing `HelpAdminPageController` pattern).

**Location:** Main domain routes at `/admin/*` (in `routes/platform.php`), not on tenant subdomains.

### 6.1 Routes

```php
// routes/platform.php — add admin route group
Route::prefix('admin')
    ->middleware(['auth', 'can:super-admin'])
    ->group(function () {
        // Dashboard
        Route::get('/', [PlatformAdminController::class, 'dashboard'])
            ->name('admin.dashboard');

        // Tenant management
        Route::get('/tenants', [PlatformAdminController::class, 'tenants'])
            ->name('admin.tenants.index');
        Route::get('/tenants/{tenant}', [PlatformAdminController::class, 'showTenant'])
            ->name('admin.tenants.show');

        // Subscription overrides
        Route::post('/tenants/{tenant}/extend-trial', [PlatformAdminController::class, 'extendTrial'])
            ->name('admin.tenants.extend-trial');
        Route::post('/tenants/{tenant}/assign-plan', [PlatformAdminController::class, 'assignPlan'])
            ->name('admin.tenants.assign-plan');
        Route::post('/tenants/{tenant}/cancel-subscription', [PlatformAdminController::class, 'cancelSubscription'])
            ->name('admin.tenants.cancel-subscription');

        // Plan management
        Route::get('/plans', [PlatformAdminController::class, 'plans'])
            ->name('admin.plans.index');
        Route::post('/plans/{plan}/toggle', [PlatformAdminController::class, 'togglePlan'])
            ->name('admin.plans.toggle');

        // Custom plan management
        Route::get('/plans/custom/create', [PlatformAdminController::class, 'createCustomPlan'])
            ->name('admin.plans.custom.create');
        Route::post('/plans/custom', [PlatformAdminController::class, 'storeCustomPlan'])
            ->name('admin.plans.custom.store');
        Route::get('/plans/custom/{plan}/edit', [PlatformAdminController::class, 'editCustomPlan'])
            ->name('admin.plans.custom.edit');
        Route::put('/plans/custom/{plan}', [PlatformAdminController::class, 'updateCustomPlan'])
            ->name('admin.plans.custom.update');
    });
```

### 6.2 Controller

```php
// app/Http/Controllers/PlatformAdminController.php
class PlatformAdminController extends Controller
{
    // Dashboard — aggregate stats
    public function dashboard(): Response
    {
        // Total tenants, active subscriptions, active trials, expired trials
        // Subscriptions by plan (Starter/Professional/Enterprise counts)
        // MRR calculation: sum of (quantity × price_per_unit) for active subscriptions
        // Recent registrations (last 30 days)
        // Trial conversion rate
    }

    // Tenant list — searchable, filterable
    public function tenants(Request $request): Response
    {
        // Search by name, slug, or admin email
        // Filter by: plan (starter/professional/enterprise/none),
        //   status (active/trial/expired/cancelled),
        //   date range (created_at)
        // Sort by: name, created_at, employee_count_cache, plan
        // Paginated with: name, slug, plan name, subscription status,
        //   employee count, trial_ends_at, created_at
    }

    // Tenant detail — full info + subscription history
    public function showTenant(Tenant $tenant): Response
    {
        // Tenant info: name, slug, created_at, database name
        // Current plan + subscription status
        // Usage stats: employee_count_cache, admin user count, device count
        // Plan limits vs current usage (visual comparison)
        // Subscription history (all subscription records)
        // Admin users list (from tenant_user pivot where role=admin)
        // Available actions: extend trial, assign plan, cancel subscription
    }

    // Extend or grant trial
    public function extendTrial(Request $request, Tenant $tenant): RedirectResponse
    {
        // Validates: days (integer, 1-90)
        // Sets trial_ends_at = now()->addDays($days) or extends existing
        // If no plan assigned, assigns Professional plan
    }

    // Override plan assignment (without PayMongo)
    public function assignPlan(Request $request, Tenant $tenant): RedirectResponse
    {
        // Validates: plan_id (exists in plans)
        // Updates tenant.plan_id directly
        // For sales-assisted deals, custom arrangements, or support overrides
        // Does NOT create/modify PayMongo subscription
    }

    // Cancel subscription on behalf of tenant
    public function cancelSubscription(Tenant $tenant): RedirectResponse
    {
        // Calls PayMongoSubscriptionService::cancel() if active subscription exists
        // Updates local subscription status
    }

    // Create custom plan form
    public function createCustomPlan(): Response
    {
        // Renders form with: plan name, module picker (checkboxes for all 21 modules),
        //   per-employee rate, limits (employees, users, devices, storage),
        //   optional tenant assignment, billing method, contract term
    }

    // Store custom plan
    public function storeCustomPlan(Request $request): RedirectResponse
    {
        // Validates all fields, creates Plan with is_custom = true
        // Creates PlanPrice records for selected billing intervals
        // Creates PlanModule records for selected modules
        // Optionally assigns tenant_id if plan is exclusive to one tenant
        // If tenant specified, can auto-assign via tenant.plan_id
    }

    // Edit custom plan
    public function editCustomPlan(Plan $plan): Response
    {
        // Only editable if plan.is_custom = true
        // Shows current modules, limits, pricing for editing
    }

    // Update custom plan
    public function updateCustomPlan(Request $request, Plan $plan): RedirectResponse
    {
        // Validates changes, updates plan, modules, prices
        // If tenant is actively using this plan, recalculates PayMongo plan amount
    }
}
```

### 6.3 Dashboard Page

**File:** `resources/js/pages/Admin/Dashboard.vue`

Overview stats displayed as card grid:

| Stat | Description |
|------|-------------|
| Total Tenants | Count of all registered tenants |
| Active Subscriptions | Tenants with active PayMongo subscription |
| Active Trials | Tenants on trial (trial_ends_at in future, no subscription) |
| Expired Trials | Tenants with expired trial and no subscription |
| MRR | Monthly Recurring Revenue calculated from active subscriptions |
| Subscriptions by Plan | Breakdown: Starter / Professional / Enterprise counts |
| Trial Conversion Rate | % of expired trials that converted to paid subscription |
| Recent Registrations | Tenants registered in the last 30 days |

### 6.4 Tenant List Page

**File:** `resources/js/pages/Admin/Tenants/Index.vue`

Follows the existing `AuditLogs/Index.vue` table pattern:
- Search input (name, slug, admin email)
- Filter dropdowns: Plan, Status (Active, Trial, Expired, Cancelled, No Plan)
- Sortable columns: Name, Plan, Status, Employees, Created
- Each row links to tenant detail page
- Pagination

### 6.5 Tenant Detail Page

**File:** `resources/js/pages/Admin/Tenants/Show.vue`

Sections:
- **Tenant Info** — Name, slug, created date, timezone, database name
- **Subscription Status** — Current plan, billing interval, status badge, next billing date, PayMongo IDs
- **Usage vs Limits** — Progress bars showing employees/users/devices against plan limits
- **Admin Users** — List of users with admin role in this tenant
- **Subscription History** — Table of all subscription records (plan, status, dates, amounts)
- **Actions Panel** — Buttons for:
  - Extend Trial (input: number of days)
  - Assign Plan (dropdown: available plans)
  - Cancel Subscription (with confirmation)
  - Impersonate (redirect to tenant subdomain as super admin)

### 6.6 Admin Layout

**File:** `resources/js/layouts/admin/Layout.vue`

Follows the existing `settings/Layout.vue` sidebar pattern:
- Sidebar with navigation: Dashboard, Tenants, Plans
- Uses `AppLayout` as parent (consistent header/auth)
- Super admin indicator in header

### 6.7 Impersonation

Super admins can access any tenant's subdomain without being a member. This already works because all permission checks in the codebase have super admin bypass:

```php
// Existing pattern in User model
public function hasPermission(Permission $permission): bool
{
    if ($this->isSuperAdmin()) {
        return true;
    }
    // ...
}
```

The "Impersonate" button generates a secure redirect token (same pattern as `TenantRegistrationController`) and redirects to `{tenant}.kasamahr.test/?token={token}`.

### Files to Create

| File | Purpose |
|------|---------|
| `app/Http/Controllers/PlatformAdminController.php` | Admin dashboard + tenant/subscription management |
| `resources/js/pages/Admin/Dashboard.vue` | Platform overview stats |
| `resources/js/pages/Admin/Tenants/Index.vue` | Searchable tenant list |
| `resources/js/pages/Admin/Tenants/Show.vue` | Tenant detail + subscription actions |
| `resources/js/layouts/admin/Layout.vue` | Admin sidebar layout |

### Files to Modify

| File | Change |
|------|--------|
| `routes/platform.php` | Add `/admin/*` route group |

### Implementation Priority

Implement **after Phase 4** (Billing UI) since the admin dashboard depends on the billing models and services being in place. Suggested timeline: Phase 5 or 6, alongside employee sync and testing.

---

## Part 7: Planned Features — Web Kiosk Clock-In

### Overview

A browser-based clock-in/clock-out terminal that provides a software-only alternative to biometric hardware for recording attendance. Designed for companies that don't have biometric devices, remote/satellite offices, or as a fallback when devices are offline.

**Available on:** All tiers (Starter, Professional, Enterprise)

### Use Cases

- **Small companies** on Starter that don't want to invest in biometric hardware
- **Remote workers** who can't access a physical device
- **Satellite offices** with few employees where a dedicated device isn't cost-effective
- **Device downtime** fallback when biometric hardware is offline or being serviced

### Feature Design

#### Kiosk Terminal Mode

A dedicated full-screen web page designed to run on a shared tablet or computer at an office entrance.

- **URL:** `https://{tenant}.kasamahr.com/kiosk` (or a unique kiosk token URL)
- **Authentication:** Kiosk session authenticated by an admin, individual employees identify via a dedicated **Kiosk PIN**
- **Flow:**
  1. Employee enters their 4–6 digit Kiosk PIN on the kiosk screen
  2. System identifies the employee and shows their name, photo, and employee code for confirmation
  3. Employee taps "Clock In" or "Clock Out"
  4. System creates an `AttendanceLog` record with `source = 'kiosk'`
  5. Confirmation screen shown briefly, then returns to the PIN entry screen

#### Kiosk PIN

A dedicated numeric PIN used exclusively for kiosk identification, separate from the employee's login credentials.

- **Auto-generated:** A random 4–6 digit PIN is generated when the kiosk feature is enabled for a tenant, or when a new employee is created
- **Stored securely:** Hashed in the database (like a password) using `Hash::make()`
- **Shown once:** Displayed to the employee via their Self-Service dashboard under "My DTR → Kiosk PIN", or provided by HR during onboarding
- **Resettable:** By the employee themselves (via Self-Service) or by an HR admin
- **Unique per tenant:** Validated with a unique constraint scoped to the tenant database

```php
// employees table (tenant DB migration)
$table->string('kiosk_pin')->nullable();          // hashed PIN
$table->timestamp('kiosk_pin_changed_at')->nullable();
```

**Why not use employee code?** Employee codes are often sequential (EMP-0001, EMP-0002) and easy to guess. A random PIN prevents colleagues from clocking in for each other. Combined with the photo confirmation step, this provides reasonable identity verification for a shared terminal.

#### Self-Service Clock-In (Optional Enhancement)

Allow employees to clock in/out from their own device via the Employee Self-Service dashboard.

- Available under "My DTR" section
- Creates `AttendanceLog` with `source = 'self_service'`
- Location verification is configurable per work location (see below)

#### Location Verification (Self-Service)

Self-service clock-in can be restricted by location to prevent remote abuse. Admins configure which checks apply per work location.

| Method | How It Works | Best For | Spoofing Risk |
|--------|-------------|----------|:-------------:|
| IP Whitelist | Check if employee's IP matches approved office network IPs | Office workers on static IP networks | Low |
| Geofencing (GPS) | Browser Geolocation API returns lat/lng; backend calculates Haversine distance to office coordinates and rejects if beyond allowed radius | Field/mobile workers, satellite offices | Medium |
| Both required | Must pass IP check AND GPS check | High-security environments | Low |
| Any (default) | Pass either IP or GPS check | Flexible office policies | Medium |
| None | No location restriction | Fully remote teams | N/A |

**Geofencing implementation:**
- Frontend uses `navigator.geolocation.getCurrentPosition()` with `enableHighAccuracy: true`
- Coordinates and accuracy are submitted with the clock-in request
- Backend rejects if `accuracy > 200m` (unreliable position data)
- Backend calculates Haversine distance between employee coordinates and work location
- Rejects if distance exceeds the configured radius (e.g., 150m)

**GPS spoofing mitigations:**
- Reject low-accuracy readings (`coords.accuracy` reported as 0 or >200m)
- Combine GPS with IP whitelist (`location_check = 'both'`)
- Photo capture on clock-in for manual audit
- Anomaly detection: flag employees clocking in from locations far apart within short time windows

```php
// work_locations table (add columns)
$table->decimal('latitude', 10, 7)->nullable();    // office GPS latitude
$table->decimal('longitude', 10, 7)->nullable();   // office GPS longitude
$table->integer('geofence_radius')->nullable();     // allowed radius in meters (e.g., 150)
$table->json('ip_whitelist')->nullable();           // ["203.0.113.50", "203.0.113.51"]
$table->string('location_check')->default('any');   // "ip", "gps", "both", "any", "none"
```

### Anti-Fraud Measures

| Measure | Description |
|---------|-------------|
| Kiosk PIN | Dedicated random numeric PIN per employee, hashed in DB — not guessable like sequential employee codes |
| IP Whitelisting | Restrict kiosk and self-service clock-in to specific office IP addresses |
| Geofencing (GPS) | Require GPS location within a radius of the office; reject if `accuracy > 200m` |
| GPS Accuracy Check | Reject geolocation readings with poor accuracy (spoofed locations often report 0 or extreme values) |
| Photo Capture | Take webcam photo on clock-in for verification |
| Cooldown Period | Prevent duplicate clock-ins within a configurable window (e.g., 5 minutes) |
| Admin Audit | All kiosk/self-service entries flagged with `source` for easy audit filtering |
| Device Binding | Bind kiosk sessions to specific device fingerprints |
| Anomaly Detection | Flag employees clocking in from locations far apart within short time windows |

### Technical Changes

#### Database

Add `source` column to `attendance_logs` table:

```php
Schema::table('attendance_logs', function (Blueprint $table) {
    $table->string('source')->default('biometric')->after('direction');
    // Values: 'biometric', 'kiosk', 'self_service', 'manual'
});
```

Add `kiosk_pin` to `employees` table:

```php
Schema::table('employees', function (Blueprint $table) {
    $table->string('kiosk_pin')->nullable();
    $table->timestamp('kiosk_pin_changed_at')->nullable();
});
```

Add `kiosks` table for registered kiosk terminals:

```php
Schema::create('kiosks', function (Blueprint $table) {
    $table->id();
    $table->string('name');                    // "Main Entrance", "2nd Floor"
    $table->string('token')->unique();         // unique access token
    $table->string('location')->nullable();    // physical location description
    $table->json('ip_whitelist')->nullable();  // allowed IP addresses
    $table->json('settings')->nullable();      // photo capture, cooldown, etc.
    $table->boolean('is_active')->default(true);
    $table->timestamp('last_activity_at')->nullable();
    $table->timestamps();
});
```

Add geofencing columns to `work_locations` table:

```php
Schema::table('work_locations', function (Blueprint $table) {
    $table->decimal('latitude', 10, 7)->nullable();
    $table->decimal('longitude', 10, 7)->nullable();
    $table->integer('geofence_radius')->nullable();     // meters
    $table->json('ip_whitelist')->nullable();
    $table->string('location_check')->default('any');   // "ip", "gps", "both", "any", "none"
});
```

#### New Files

| File | Purpose |
|------|---------|
| `app/Models/Kiosk.php` | Kiosk terminal model |
| `app/Http/Controllers/KioskController.php` | Kiosk management (admin CRUD) |
| `app/Http/Controllers/KioskTerminalController.php` | Kiosk terminal UI and clock-in/out logic |
| `app/Http/Middleware/ValidateKioskToken.php` | Authenticate kiosk sessions via token |
| `resources/js/pages/Kiosk/Terminal.vue` | Full-screen kiosk clock-in UI |
| `resources/js/pages/Kiosk/Index.vue` | Admin kiosk management page |
| `resources/js/pages/Kiosk/Create.vue` | Admin kiosk registration form |
| `database/migrations/tenant/xxxx_add_kiosk_pin_to_employees.php` | Add kiosk_pin and kiosk_pin_changed_at columns |
| `database/migrations/tenant/xxxx_add_source_to_attendance_logs.php` | Add source column |
| `database/migrations/tenant/xxxx_create_kiosks_table.php` | Kiosks table |
| `database/migrations/tenant/xxxx_add_geofence_to_work_locations.php` | Add latitude, longitude, geofence_radius, ip_whitelist, location_check |

#### Modified Files

| File | Change |
|------|--------|
| `app/Services/Dtr/DtrCalculationService.php` | Handle kiosk and self-service attendance log entries (same punch logic, different source) |
| `app/Models/AttendanceLog.php` | Add `source` attribute, scope for filtering by source |
| `app/Models/Employee.php` | Add `kiosk_pin` (hidden), PIN verification method, PIN generation |
| `app/Models/WorkLocation.php` | Add geofence attributes, `isWithinGeofence(lat, lng)` method |
| `resources/js/components/TenantSidebar.vue` | Add kiosk management link under Time & Attendance |
| `resources/js/pages/SelfService/MyDtr.vue` | Add clock-in/out button (if self-service clock-in enabled), Kiosk PIN display/reset |

#### Kiosk Limits per Tier

| Tier | Max Kiosks |
|------|:----------:|
| Starter | 1 |
| Professional | 5 |
| Enterprise | Unlimited |

### Implementation Priority

This feature should be implemented **after** the core SaaS billing system (Parts 2–6) is complete. Suggested timeline: Phase 8 (Week 9–10), after the main subscription system and platform admin dashboard are live and tested.
