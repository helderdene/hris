# Visitor Management Module — Design Document

## Context

KasamaHR needs a Visitor Management module to track external visitors entering tenant premises. The module supports two registration paths: **visitor-initiated** (public registration page with host approval) and **admin-initiated** (pre-registration by front desk). It integrates with the existing FR (biometric) device and Kiosk systems for automated check-in, and supports QR codes for contactless check-in. It belongs to the **Starter tier** (available on all paid plans).

**Current state:** No visitor tracking capability. Visitors are managed informally outside the system.

**Target state:** Full visitor lifecycle management with dual registration paths (visitor self-registration + admin pre-registration), host approval workflow, automated check-in (FR device, Kiosk QR, manual), host notifications, and visitor logs with export.

---

## Part 1: Scope & User Stories

### 1.1 Core User Stories

| # | As a... | I want to... | So that... |
|---|---------|--------------|------------|
| 1 | Visitor | Visit a public registration page to register my upcoming visit | I can pre-register without needing to contact the company directly |
| 2 | System | Notify the front desk admin and host employee about a new visitor registration | They can review and approve or reject the visit request |
| 3 | Host employee | Approve or reject a visitor registration request | Only authorized visitors are granted access |
| 4 | System | On approval: create the visit record, sync photo to FR device (if uploaded), generate QR code, and send confirmation email to visitor | The visitor receives everything they need for a smooth check-in |
| 5 | Front desk admin | Pre-register visitors with optional photo upload (admin-initiated) | I can register expected visitors on behalf of the company |
| 6 | Visitor | Check in via FR device recognition | I don't need to stop at the front desk |
| 7 | Visitor | Check in by scanning a QR code at a Kiosk | I can quickly identify myself without a PIN |
| 8 | Front desk admin | Manually check in a visitor | Walk-in visitors without pre-registration can be recorded |
| 9 | Host employee | Receive arrival/departure notifications | I know when my visitor has arrived or left |
| 10 | HR/Admin | Search, filter, and export the visitor log | I can audit visitor traffic and generate reports |

### 1.2 Registration Paths

| Path | Initiated by | Approval Required | Flow |
|------|-------------|:-----------------:|------|
| **Visitor Self-Registration** | Visitor (public page) | Yes — host employee or admin | Visitor registers → admin+host notified → host approves → QR + confirmation sent to visitor |
| **Admin Pre-Registration** | Front desk admin | No (already authorized) | Admin creates visit → QR + confirmation sent to visitor immediately |

### 1.3 Check-in Methods

| Method | Description | Requires Approved Visit |
|--------|-------------|:-----------------------:|
| **FR Device** | Visitor photo synced to FR devices; device recognizes visitor on arrival | Yes (with photo) |
| **Kiosk QR Scan** | Visitor scans QR code from confirmation email at a Kiosk terminal | Yes |
| **Manual** | Front desk admin searches for visitor or creates a walk-in entry | No |

---

## Part 2: Data Model

### 2.1 `visitors` table (tenant DB)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | PK |
| `first_name` | string(255) | Required |
| `last_name` | string(255) | Required |
| `email` | string(255) | Nullable |
| `phone` | string(50) | Nullable |
| `company` | string(255) | Nullable |
| `id_type` | string(100) | Nullable (drivers_license, passport, etc.) |
| `id_number` | string(255) | Nullable |
| `photo_path` | string(500) | Nullable, for FR sync |
| `notes` | text | Nullable |
| `metadata` | json | Nullable |
| `timestamps` | | |

**Indexes:** `email`, composite `(last_name, first_name)`

### 2.2 `visitor_visits` table (tenant DB)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | PK |
| `visitor_id` | foreignId | FK visitors, cascade delete |
| `work_location_id` | foreignId | FK work_locations, cascade delete |
| `host_employee_id` | foreignId | Nullable FK employees, null on delete |
| `purpose` | string(500) | Required |
| `status` | string(50) | Cast: `VisitStatus` enum |
| `registration_source` | string(50) | `'visitor'` or `'admin'` — who initiated the registration |
| `expected_at` | datetime | Nullable |
| `approved_at` | datetime | Nullable, set when host/admin approves |
| `approved_by` | foreignId | Nullable FK users, null on delete |
| `rejected_at` | datetime | Nullable, set when host/admin rejects |
| `rejection_reason` | string(500) | Nullable |
| `checked_in_at` | datetime | Nullable |
| `checked_out_at` | datetime | Nullable |
| `check_in_method` | string(50) | Nullable, cast: `CheckInMethod` enum |
| `checked_in_by` | foreignId | Nullable FK users, null on delete |
| `biometric_device_id` | foreignId | Nullable FK biometric_devices, null on delete |
| `kiosk_id` | foreignId | Nullable FK kiosks, null on delete |
| `qr_token` | string(64) | Unique nullable, generated on approval |
| `registration_token` | string(64) | Unique, for public registration page URL |
| `badge_number` | string(50) | Nullable |
| `host_notified_at` | datetime | Nullable |
| `notes` | text | Nullable |
| `timestamps` | | |

**Indexes:** `qr_token` (unique), `registration_token` (unique), `status`, `work_location_id`, `expected_at`

### 2.3 `visitor_device_syncs` table (tenant DB)

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigIncrements | PK |
| `visitor_id` | foreignId | FK visitors, cascade delete |
| `biometric_device_id` | foreignId | FK biometric_devices, cascade delete |
| `status` | string(50) | pending/syncing/synced/failed |
| `last_synced_at` | datetime | Nullable |
| `last_error` | text | Nullable |
| `message_id` | string(255) | Nullable, MQTT message ID |
| `timestamps` | | |

**Unique constraint:** `(visitor_id, biometric_device_id)`

---

## Part 3: Enums

### 3.1 `VisitStatus`

```php
// app/Enums/VisitStatus.php
enum VisitStatus: string
{
    case PendingApproval = 'pending_approval';  // Visitor-initiated, awaiting host/admin approval
    case Approved = 'approved';                  // Approved, QR sent, waiting for arrival
    case PreRegistered = 'pre_registered';       // Admin-initiated, pre-registered (no approval needed)
    case CheckedIn = 'checked_in';
    case CheckedOut = 'checked_out';
    case Rejected = 'rejected';                  // Host/admin rejected the visit request
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
}
```

**Status transitions:**

```
Visitor-initiated:  PendingApproval → Approved → CheckedIn → CheckedOut
                    PendingApproval → Rejected
Admin-initiated:    PreRegistered → CheckedIn → CheckedOut
Either:             * → Cancelled
                    Approved/PreRegistered → NoShow (scheduled cleanup)
```

### 3.2 `CheckInMethod`

```php
// app/Enums/CheckInMethod.php
enum CheckInMethod: string
{
    case Biometric = 'biometric';
    case Kiosk = 'kiosk';
    case Manual = 'manual';
}
```

### 3.3 Module Enum Update

Add `VisitorManagement = 'visitor_management'` to the Starter tier modules in `app/Enums/Module.php`.

---

## Part 4: Backend Architecture

### 4.1 Models

All models extend `TenantModel` (tenant database connection).

#### `Visitor`

- **Relationships:** `visits()`, `deviceSyncs()`
- **Accessors:** `fullName` (computed from first_name + last_name)
- **Scopes:** `scopeSearch($query, $term)` — searches first_name, last_name, email, company

#### `VisitorVisit`

- **Relationships:** `visitor()`, `workLocation()`, `hostEmployee()`, `checkedInBy()`, `biometricDevice()`, `kiosk()`
- **Scopes:** `active()` (checked in, not checked out), `today()`, `atLocation($locationId)`

#### `VisitorDeviceSync`

- **Relationships:** `visitor()`, `biometricDevice()`
- **Methods:** Status mutation methods (`markSyncing()`, `markSynced()`, `markFailed($error)`)

### 4.2 Controllers

| Controller | Type | Methods |
|-----------|------|---------|
| `VisitorController` (web) | Inertia | `index()`, `log()` |
| `Api\VisitorController` | API | `index`, `store`, `show`, `update`, `destroy` |
| `Api\VisitorVisitController` | API | `index`, `store`, `show`, `update`, `destroy`, `approve`, `reject`, `checkIn`, `checkOut`, `resendQrCode`, `export` |
| `VisitorRegistrationController` | Public | `show` (registration form page), `store` (submit registration) |
| `KioskTerminalController` (modify) | Public | Add `visitorCheckIn`, `visitorCheckOut` |

### 4.3 Services

#### `VisitorRegistrationService`

Handles both visitor-initiated and admin-initiated registration paths.

```php
// app/Services/Visitor/VisitorRegistrationService.php
class VisitorRegistrationService
{
    /** Visitor-initiated: creates visit with PendingApproval status, notifies admin + host */
    public function registerFromPublicPage(array $visitorData, array $visitData): VisitorVisit;

    /** Admin-initiated: creates visit with PreRegistered status, generates QR, sends email to visitor */
    public function preRegister(Visitor $visitor, array $visitData, User $admin): VisitorVisit;

    /** Approve a pending visit: generates QR, syncs FR (if photo), sends confirmation to visitor */
    public function approve(VisitorVisit $visit, User $approver): VisitorVisit;

    /** Reject a pending visit: notifies visitor with reason */
    public function reject(VisitorVisit $visit, User $rejector, ?string $reason = null): VisitorVisit;

    public function generateQrToken(): string;         // 64-char cryptographically random
    public function generateRegistrationToken(): string; // 64-char for public page URL
    public function resendConfirmationEmail(VisitorVisit $visit): void;
}
```

#### `VisitorCheckInService`

Centralized check-in logic for all methods.

```php
// app/Services/Visitor/VisitorCheckInService.php
class VisitorCheckInService
{
    public function checkInManual(VisitorVisit $visit, User $user, ?string $badgeNumber = null): VisitorVisit;
    public function checkInViaKiosk(VisitorVisit $visit, Kiosk $kiosk): VisitorVisit;
    public function checkInViaBiometric(VisitorVisit $visit, BiometricDevice $device): VisitorVisit;
    public function checkOut(VisitorVisit $visit, ?User $user = null): VisitorVisit;
}
```

Each method validates that the visit is in an approved/pre-registered status, updates `checked_in_at`/`checked_out_at`, sets `check_in_method`, and dispatches host notifications.

#### `VisitorDeviceSyncService`

```php
// app/Services/Visitor/VisitorDeviceSyncService.php
class VisitorDeviceSyncService
{
    public function syncVisitorToDevice(Visitor $visitor, BiometricDevice $device): VisitorDeviceSync;
    public function syncVisitorToLocationDevices(Visitor $visitor, WorkLocation $location): Collection;
    public function unsyncVisitorFromDevice(Visitor $visitor, BiometricDevice $device): void;
}
```

### 4.4 Notifications

| Notification | Channel | Recipient | Trigger |
|-------------|---------|-----------|---------|
| `VisitorRegistrationRequested` | Mail + Database | Front desk admin + host employee | Visitor submits public registration form |
| `VisitorApproved` | Mail | Visitor | Host/admin approves visit → sends QR code + visit details |
| `VisitorRejected` | Mail | Visitor | Host/admin rejects visit → sends rejection reason |
| `VisitorPreRegistered` | Mail | Visitor | Admin creates visit (admin-initiated) → sends QR code + visit details |
| `VisitorArrived` | Mail + Database | Host employee | Visitor checks in |
| `VisitorCheckedOut` | Database | Host employee | Visitor checks out |

### 4.5 Jobs

| Job | Type | Description |
|-----|------|-------------|
| `SyncVisitorToDeviceJob` | Queued | MQTT sync of visitor photo to FR device via `editPerson` command |
| `CleanupVisitorDeviceSyncsJob` | Scheduled (daily) | Removes visitors from FR devices 24h after checkout |

---

## Part 5: API Endpoints

### 5.1 Authenticated (module-gated)

| Method | Path | Action |
|--------|------|--------|
| GET | `/api/visitors` | List visitors |
| POST | `/api/visitors` | Create visitor |
| GET | `/api/visitors/{visitor}` | Show visitor |
| PUT | `/api/visitors/{visitor}` | Update visitor |
| DELETE | `/api/visitors/{visitor}` | Delete visitor |
| GET | `/api/visitor-visits` | List visits (filterable by status, date, location) |
| POST | `/api/visitor-visits` | Admin pre-register visit (status = PreRegistered) |
| GET | `/api/visitor-visits/{visit}` | Show visit |
| PUT | `/api/visitor-visits/{visit}` | Update visit |
| DELETE | `/api/visitor-visits/{visit}` | Delete visit |
| POST | `/api/visitor-visits/{visit}/approve` | Approve pending visit → generates QR, syncs FR, emails visitor |
| POST | `/api/visitor-visits/{visit}/reject` | Reject pending visit → emails visitor with reason |
| POST | `/api/visitor-visits/{visit}/check-in` | Manual check-in |
| POST | `/api/visitor-visits/{visit}/check-out` | Manual check-out |
| POST | `/api/visitor-visits/{visit}/resend-qr` | Resend QR confirmation email |
| GET | `/api/visitor-visits/export` | Export CSV |

### 5.2 Public (no auth)

| Method | Path | Action |
|--------|------|--------|
| GET | `/visit/{tenant}/register` | Public visitor registration page |
| POST | `/visit/{tenant}/register` | Submit visitor registration (creates PendingApproval visit) |
| POST | `/kiosk/{token}/visitor-check-in` | Kiosk QR check-in |
| POST | `/kiosk/{token}/visitor-check-out` | Kiosk check-out |

### 5.3 Web (Inertia, module-gated)

| Method | Path | Action |
|--------|------|--------|
| GET | `/visitors` | Dashboard/list page (tabs: Pending Approval, Expected Today, Checked In, All) |
| GET | `/visitors/log` | Full visitor log |

---

## Part 6: Integration Flows

### 6.1 Visitor-Initiated Registration Flow (Primary)

```
1. Visitor visits public registration page
   └─> URL: /visit/{tenant}/register (shareable link, can be posted on website/lobby)
   └─> Fills in: name, email, phone, company, purpose, host employee (searchable),
       expected date/time, optional photo upload, work location

2. System creates VisitorVisit with status = PendingApproval
   └─> Creates or reuses Visitor record (matched by email)
   └─> Generates registration_token for tracking

3. VisitorRegistrationRequested notification sent
   └─> Notifies front desk admin (mail + database notification)
   └─> Notifies host employee (mail + database notification)
   └─> Email contains: visitor details, purpose, expected date, approve/reject links

4. Host employee or admin reviews and approves the visit
   └─> POST /api/visitor-visits/{visit}/approve
   └─> Status changes: PendingApproval → Approved
   └─> System generates qr_token (64-char random)
   └─> If visitor uploaded photo → SyncVisitorToDeviceJob dispatched (FR sync)
   └─> If no photo → only QR check-in available

5. VisitorApproved notification sent to visitor
   └─> Contains: QR code image, visit details, expected date, location/directions
   └─> Visitor can now check in via QR at kiosk or FR recognition

6. On arrival day, visitor uses one of:
   ├─> FR recognition (if photo was synced)
   ├─> QR scan at kiosk
   └─> Manual check-in at front desk

   Alternative: Host/admin rejects the visit
   └─> POST /api/visitor-visits/{visit}/reject
   └─> Status changes: PendingApproval → Rejected
   └─> VisitorRejected notification sent to visitor (with optional reason)
```

### 6.2 Admin-Initiated Pre-Registration Flow

```
1. Admin creates visit via Visitors page (VisitorPreRegisterModal)
   └─> Searches/creates visitor record
   └─> Selects host employee, work location, purpose, expected date
   └─> Optional: uploads visitor photo

2. System creates VisitorVisit with status = PreRegistered (no approval needed)
   └─> Generates qr_token immediately
   └─> If photo uploaded → SyncVisitorToDeviceJob dispatched (FR sync)

3. VisitorPreRegistered notification sent to visitor email
   └─> Contains: QR code image, visit details, expected date, location/directions

4. On arrival day, visitor uses one of:
   ├─> FR recognition (if photo was synced)
   ├─> QR scan at kiosk
   └─> Manual check-in at front desk
```

### 6.3 FR Device Check-in Flow

```
1. Photo uploaded during registration (visitor-initiated or admin-initiated)
   └─> Photo stored in tenant-scoped storage

2. On approval/creation, SyncVisitorToDeviceJob dispatched
   └─> MQTT editPerson command sent to FR device
   └─> device_person_id: "visitor-{id}" (prefixed to avoid employee ID collision)

3. Visitor arrives at office
   └─> FR device recognizes face
   └─> MQTT event published with "visitor-{id}" identifier

4. MQTT listener receives event
   └─> Routes "visitor-*" IDs to VisitorCheckInService::checkInViaBiometric()
   └─> Host employee notified via VisitorArrived notification

5. After checkout + 24h
   └─> CleanupVisitorDeviceSyncsJob removes visitor from device
```

### 6.4 Kiosk QR Check-in Flow

```
1. Kiosk Terminal UI has a "Visitor" tab alongside employee PIN entry

2. Visitor scans QR code (barcode scanner acts as keyboard input)
   └─> QR contains the qr_token value

3. POST /kiosk/{token}/visitor-check-in validates qr_token
   └─> Validates visit status is Approved or PreRegistered
   └─> Records check-in with check_in_method = 'kiosk'
   └─> Host employee notified via VisitorArrived notification
   └─> Shows confirmation screen

4. Re-scan when already checked in triggers check-out
```

---

## Part 7: Frontend

### 7.1 Pages

| File | Description |
|------|-------------|
| `resources/js/pages/Visitors/Index.vue` | Tabbed layout: Pending Approval, Expected Today, Checked In, All Visitors |
| `resources/js/pages/Visitors/Log.vue` | Full log with filters (date range, location, status, method) and CSV export |
| `resources/js/pages/Visitor/Register.vue` | Public visitor registration page (GuestLayout), form with host employee search, photo upload via camera/file |

### 7.2 Components

| File | Description |
|------|-------------|
| `VisitorPreRegisterModal.vue` | Admin pre-registration form with visitor search/create and host employee select |
| `VisitorApprovalModal.vue` | Approve/reject modal with optional rejection reason |
| `VisitorCheckInModal.vue` | Manual check-in modal with optional badge number |
| `VisitorQrCode.vue` | QR code renderer (using `qr_token` value) |
| `VisitorStatusBadge.vue` | Color-coded status badges for visit statuses |

### 7.3 Kiosk Modification

Add a "Visitor" mode tab to `resources/js/pages/Kiosk/Terminal.vue` with a QR input field (auto-focused, accepts barcode scanner input).

### 7.4 Sidebar

Add "Visitor Management" section to `TenantSidebar.vue` under Time & Attendance, gated by `hasModule('visitor_management')`:

- Visitors (link to `/visitors`)
- Visitor Log (link to `/visitors/log`)

---

## Part 8: Files to Create/Modify

### New Files (~40 files)

```
# Enums
app/Enums/VisitStatus.php
app/Enums/CheckInMethod.php

# Models + Factories
app/Models/Visitor.php
app/Models/VisitorVisit.php
app/Models/VisitorDeviceSync.php
database/factories/VisitorFactory.php
database/factories/VisitorVisitFactory.php

# Controllers
app/Http/Controllers/VisitorController.php
app/Http/Controllers/VisitorRegistrationController.php
app/Http/Controllers/Api/VisitorController.php
app/Http/Controllers/Api/VisitorVisitController.php

# Form Requests
app/Http/Requests/StoreVisitorRequest.php
app/Http/Requests/UpdateVisitorRequest.php
app/Http/Requests/StoreVisitorVisitRequest.php
app/Http/Requests/UpdateVisitorVisitRequest.php
app/Http/Requests/ApproveVisitorVisitRequest.php
app/Http/Requests/RejectVisitorVisitRequest.php
app/Http/Requests/CheckInVisitorRequest.php
app/Http/Requests/VisitorRegistrationRequest.php

# Resources
app/Http/Resources/VisitorResource.php
app/Http/Resources/VisitorVisitResource.php

# Services
app/Services/Visitor/VisitorRegistrationService.php
app/Services/Visitor/VisitorCheckInService.php
app/Services/Visitor/VisitorDeviceSyncService.php

# Notifications & Jobs
app/Notifications/VisitorRegistrationRequested.php
app/Notifications/VisitorApproved.php
app/Notifications/VisitorRejected.php
app/Notifications/VisitorPreRegistered.php
app/Notifications/VisitorArrived.php
app/Notifications/VisitorCheckedOut.php
app/Jobs/SyncVisitorToDeviceJob.php
app/Jobs/CleanupVisitorDeviceSyncsJob.php

# Migrations
database/migrations/tenant/2026_02_20_000001_create_visitors_table.php
database/migrations/tenant/2026_02_20_000002_create_visitor_visits_table.php
database/migrations/tenant/2026_02_20_000003_create_visitor_device_syncs_table.php

# Routes
routes/tenant/web-visitors.php
routes/tenant/api-visitors.php

# Frontend
resources/js/pages/Visitors/Index.vue
resources/js/pages/Visitors/Log.vue
resources/js/pages/Visitor/Register.vue
resources/js/components/VisitorPreRegisterModal.vue
resources/js/components/VisitorApprovalModal.vue
resources/js/components/VisitorCheckInModal.vue
resources/js/components/VisitorQrCode.vue
resources/js/components/VisitorStatusBadge.vue

# Tests
tests/Feature/VisitorCrudTest.php
tests/Feature/VisitorVisitCrudTest.php
tests/Feature/VisitorRegistrationTest.php
tests/Feature/VisitorApprovalTest.php
tests/Feature/VisitorCheckInTest.php
tests/Feature/VisitorKioskCheckInTest.php
tests/Feature/VisitorDeviceSyncTest.php
tests/Feature/VisitorNotificationTest.php
```

### Files to Modify

| File | Change |
|------|--------|
| `app/Enums/Module.php` | Add `VisitorManagement = 'visitor_management'` to Starter tier |
| `routes/tenant.php` | Register public visitor registration route + require route files |
| `app/Http/Controllers/KioskTerminalController.php` | Add `visitorCheckIn`, `visitorCheckOut` methods |
| `resources/js/pages/Kiosk/Terminal.vue` | Add visitor mode tab with QR input |
| `resources/js/components/TenantSidebar.vue` | Add Visitor Management section |
| `resources/js/types/index.d.ts` | Add `VisitorData`, `VisitorVisitData` types |
| `app/Services/Biometric/DeviceCommandService.php` | Add `editVisitorPerson()` for MQTT sync |
| `routes/console.php` | Schedule `CleanupVisitorDeviceSyncsJob` |
| `app/Http/Middleware/HandleInertiaRequests.php` | Share public visitor registration URL in tenant context |

---

## Part 9: Security

| Concern | Mitigation |
|---------|------------|
| QR/self-registration tokens | 64-char cryptographically random strings (`Str::random(64)`) |
| Public endpoint abuse | Rate-limited: 10 req/min self-registration, 5 req/min kiosk |
| FR device ID collision | Visitor person IDs prefixed `"visitor-"` to prevent collision with employees |
| Stale device data | Visitors auto-removed from FR devices 24h after checkout |
| Tenant isolation | Visitor photos stored in tenant-scoped storage (isolated per tenant) |
| Module access | Module-gated routes prevent access without subscription |
| Token expiry | Self-registration tokens can be invalidated after use or after a configurable expiry |

---

## Part 10: Implementation Sequence

### Phase 1: Foundation
- Module enum update, migrations, models, enums, factories

### Phase 2: Core CRUD
- Visitor + VisitorVisit API controllers, form requests, resources, routes, tests

### Phase 3: Public Registration + Approval Workflow
- `VisitorRegistrationController` (public page), `VisitorRegistrationService`, approval/rejection endpoints, `VisitorRegistrationRequested` / `VisitorApproved` / `VisitorRejected` notifications, public `Register.vue` page, tests

### Phase 4: Admin Pre-Registration
- Admin pre-registration flow (no approval needed), `VisitorPreRegistered` notification, `VisitorPreRegisterModal.vue`, tests

### Phase 5: Kiosk Integration
- Extend `KioskTerminalController` + `Terminal.vue`, tests

### Phase 6: FR Device Integration
- `DeviceCommandService` extension, sync service/job, MQTT handler, cleanup job, tests

### Phase 7: Frontend
- Visitors pages (Index with Pending Approval tab, Log), approval modal, check-in modal, sidebar update, TypeScript types

### Phase 8: Arrival/Departure Notifications
- `VisitorArrived` + `VisitorCheckedOut` host notifications, tests

---

## Part 11: Verification

| Check | Method |
|-------|--------|
| Unit/feature tests | `php artisan test --filter=Visitor` after each phase |
| Public registration flow | Visit `/visit/{tenant}/register`, submit form, verify admin+host notified |
| Approval flow | Approve pending visit, verify QR generated and visitor emailed |
| Rejection flow | Reject pending visit, verify visitor emailed with reason |
| Admin pre-registration | Create visit via admin, verify QR sent immediately (no approval step) |
| Kiosk visitor flow | Manual test via `/kiosk/{token}` with a QR token |
| FR sync | Check MQTT messages in debug session (`mcp__herd__start_debug_session`) |
| Module gating | Toggle `visitor_management` in tenant's plan and verify access control |
| Full regression | `php artisan test` at the end |
