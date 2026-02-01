# KasamaHR — Complete Implementation Instructions

---

## About These Instructions

**What you're receiving:**
- Finished UI designs (React components with full styling)
- Data model definitions (TypeScript types and sample data)
- UI/UX specifications (user flows, requirements, screenshots)
- Design system tokens (colors, typography, spacing)
- Test-writing instructions for each section (for TDD approach)

**What you need to build:**
- Backend API endpoints and database schema
- Authentication and authorization
- Data fetching and state management
- Business logic and validation
- Integration of the provided UI components with real data

**Important guidelines:**
- **DO NOT** redesign or restyle the provided components — use them as-is
- **DO** wire up the callback props to your routing and API calls
- **DO** replace sample data with real data from your backend
- **DO** implement proper error handling and loading states
- **DO** implement empty states when no records exist (first-time users, after deletions)
- **DO** use test-driven development — write tests first using `tests.md` instructions
- The components are props-based and ready to integrate — focus on the backend and data layer

---

## Test-Driven Development

Each section includes a `tests.md` file with detailed test-writing instructions. These are **framework-agnostic** — adapt them to your testing setup (Jest, Vitest, Playwright, Cypress, RSpec, Minitest, PHPUnit, etc.).

**For each section:**
1. Read `product-plan/sections/[section-id]/tests.md`
2. Write failing tests for key user flows (success and failure paths)
3. Implement the feature to make tests pass
4. Refactor while keeping tests green

The test instructions include:
- Specific UI elements, button labels, and interactions to verify
- Expected success and failure behaviors
- Empty state handling (when no records exist yet)
- Data assertions and state validations

---

# Product Overview

## Summary

A multi-tenant SaaS platform for complete human resource lifecycle management, designed specifically for the Philippine market. Targeting mid-market to enterprise organizations in manufacturing, construction, and government sectors, KasamaHR provides full compliance with Philippine labor laws and government regulatory requirements including BIR, SSS, PhilHealth, and Pag-IBIG integrations.

## Key Features

- Employee Information Management (digital 201 file)
- Time & Attendance with MQTT-based facial recognition devices
- Payroll processing with Philippine tax compliance
- Leave management with statutory leave types (SIL, Maternity, Paternity, Solo Parent, VAWC)
- Employee Self-Service Portal
- Multi-tenant architecture with subdomain-based routing

## Implementation Sequence

1. **Foundation** — Design tokens, data model, routing, application shell
2. **Employee Management** — Core employee records, 201 file, organization structure
3. **Time & Attendance** — Work schedules, biometric integration, DTR processing
4. **Payroll & Compliance** — Payroll computation, government deductions, regulatory reports
5. **Leave Management** — Leave types, balance tracking, approval workflows
6. **Self-Service Portal** — Employee/manager self-service features

---

# Milestone 1: Foundation

## Goal

Set up the foundational elements: design tokens, data model types, routing structure, and application shell.

## What to Implement

### 1. Design Tokens

Configure your styling system with these tokens:

- See `product-plan/design-system/tokens.css` for CSS custom properties
- See `product-plan/design-system/tailwind-colors.md` for Tailwind configuration
- See `product-plan/design-system/fonts.md` for Google Fonts setup

**Color Palette:**
- Primary: `blue` — Buttons, links, active states
- Secondary: `emerald` — Success indicators, positive badges
- Neutral: `slate` — Backgrounds, text, borders

### 2. Data Model Types

Create TypeScript interfaces for your core entities:

- See `product-plan/data-model/types.ts` for interface definitions
- See `product-plan/data-model/README.md` for entity relationships

**Key Entities:**
- Tenant, Employee, Department, Position
- WorkSchedule, AttendanceLog, DailyTimeRecord
- PayrollPeriod, PayrollRecord, PayrollDeduction
- LeaveType, LeaveBalance, LeaveApplication
- Loan, GovernmentReport, Document, User

### 3. Routing Structure

| Route | Section |
|-------|---------|
| `/` | Dashboard / Home |
| `/employees/*` | Employee Management |
| `/attendance/*` | Time & Attendance |
| `/payroll/*` | Payroll & Compliance |
| `/leaves/*` | Leave Management |
| `/self-service/*` | Self-Service Portal |
| `/settings` | System Settings |

### 4. Application Shell

Copy the shell components from `product-plan/shell/components/`:

- `AppShell.tsx` — Main layout wrapper with sidebar and header
- `MainNav.tsx` — Navigation component with icons
- `UserMenu.tsx` — User menu with avatar and dropdown

**Navigation Items:**

| Nav Item | Route |
|----------|-------|
| Dashboard | `/` |
| Employees | `/employees` |
| Attendance | `/attendance` |
| Payroll | `/payroll` |
| Leaves | `/leaves` |
| Self-Service | `/self-service` |
| Settings | `/settings` |

### 5. Multi-Tenancy Setup

- Each tenant has a unique subdomain (e.g., `acme.kasamahr.com`)
- Tenant data is isolated in the database
- Implement tenant resolution middleware

## Done When

- [ ] Design tokens are configured (colors, typography)
- [ ] Google Fonts are loaded (DM Sans, JetBrains Mono)
- [ ] Data model types are defined
- [ ] Routes exist for all sections
- [ ] Shell renders with navigation
- [ ] Sidebar can collapse/expand
- [ ] Responsive on mobile

---

# Milestone 2: Employee Management

## Goal

Implement the Employee Management section — the central repository for all employee-related data (digital 201 file).

## Overview

This section enables HR administrators to manage employee records, organizational structure, and employment documents.

**Key Functionality:**
- View employee directory with search, filter, and export
- Access comprehensive employee profiles
- Add new employees with complete onboarding data
- Manage 201 file documents

## Components

- `EmployeeDashboard` — KPI cards with headcount, turnover, new hires
- `EmployeeList` — Paginated table with search, filters, sorting
- `EmployeeProfile` — Tabbed view with personal, employment, government, documents
- `StatCard` — Reusable stat card component

## Callbacks

| Callback | Description |
|----------|-------------|
| `onViewEmployee` | Navigate to employee profile |
| `onAddEmployee` | Open create employee form |
| `onEditEmployee` | Open edit employee modal |
| `onDeleteEmployee` | Confirm and delete employee |
| `onExportEmployees` | Download CSV/Excel export |
| `onUploadDocument` | Upload 201 file document |
| `onDownloadDocument` | Download document file |

## Files to Reference

- `product-plan/sections/employee-management/tests.md`
- `product-plan/sections/employee-management/components/`
- `product-plan/sections/employee-management/types.ts`
- `product-plan/sections/employee-management/sample-data.json`

---

# Milestone 3: Time & Attendance

## Goal

Implement comprehensive time tracking with real-time MQTT-based biometric integration and automated attendance processing.

## Overview

This section manages work schedules, processes raw attendance logs from facial recognition devices, and generates Daily Time Records (DTR).

**Key Functionality:**
- Real-time attendance monitoring dashboard
- Work schedule configuration (fixed, flexible, shifting)
- DTR generation with computed late, undertime, overtime, night differential
- DTR correction request and approval workflow

## Components

- `AttendanceDashboard` — Real-time KPIs and recent logs feed
- `DailyTimeRecordList` — DTR table with employee attendance summaries
- `WorkScheduleList` — Schedule configuration list

## Callbacks

| Callback | Description |
|----------|-------------|
| `onViewDTR` | Open DTR detail view |
| `onRequestCorrection` | Submit DTR correction request |
| `onApproveCorrection` | Approve correction request |
| `onCreateSchedule` | Create new work schedule |
| `onEditSchedule` | Modify work schedule |
| `onAssignSchedule` | Bulk assign schedule to employees |
| `onExportDTR` | Download DTR report |

## Files to Reference

- `product-plan/sections/time-and-attendance/tests.md`
- `product-plan/sections/time-and-attendance/components/`
- `product-plan/sections/time-and-attendance/types.ts`
- `product-plan/sections/time-and-attendance/sample-data.json`

---

# Milestone 4: Payroll & Compliance

## Goal

Implement full-featured payroll processing engine with Philippine tax and statutory compliance.

## Overview

This section handles payroll computation, automatic government deductions (SSS, PhilHealth, Pag-IBIG, BIR), and regulatory report generation.

**Key Functionality:**
- Payroll period management (regular, supplemental, 13th month, final pay)
- Automated payroll computation with earnings breakdown
- Government deduction calculations
- BIR, SSS, PhilHealth, Pag-IBIG report generation

## Components

- `PayrollDashboard` — KPIs with total payroll, pending periods, deadlines
- `PayrollPeriodList` — Payroll cycle list with status indicators
- `PayrollRecordList` — Individual employee payroll records
- `GovernmentReportsHub` — Report type selection and generation

## Callbacks

| Callback | Description |
|----------|-------------|
| `onCreatePeriod` | Create new payroll period |
| `onProcessPayroll` | Run payroll computation |
| `onApprovePeriod` | Approve computed payroll |
| `onViewPayrollRecord` | Open employee payroll detail |
| `onGeneratePayslip` | Create employee payslip |
| `onGenerateReport` | Generate government report |
| `onExportReport` | Download report file |

## Computation Logic

**Earnings:**
- Basic pay (monthly, semi-monthly, weekly, daily, hourly)
- Overtime (regular, rest day, special holiday, regular holiday)
- Night differential (10% premium for 10pm-6am)
- Holiday pay (per Philippine holiday calendar)

**Deductions:**
- SSS contribution (employer/employee share per schedule)
- PhilHealth contribution (current rate schedule)
- Pag-IBIG contribution (current rate schedule)
- BIR withholding tax (TRAIN Law brackets)
- Loan amortizations

## Files to Reference

- `product-plan/sections/payroll-and-compliance/tests.md`
- `product-plan/sections/payroll-and-compliance/components/`
- `product-plan/sections/payroll-and-compliance/types.ts`
- `product-plan/sections/payroll-and-compliance/sample-data.json`

---

# Milestone 5: Leave Management

## Goal

Implement comprehensive leave management system compliant with Philippine labor laws.

## Overview

This section enables employees to file leave requests, track balances, and receive approvals with support for mandatory statutory leave types.

**Key Functionality:**
- Leave dashboard with balances and pending requests
- Leave type configuration with eligibility and accrual rules
- Leave application filing with date selection
- Multi-level approval workflow
- Team/company leave calendar view

## Components

- `LeaveDashboard` — Balance cards, pending requests, mini calendar
- `LeaveTypeList` — Leave type configuration cards
- `LeaveApprovalQueue` — Pending requests for approval
- `LeaveCalendar` — Team calendar with color-coded leave

## Callbacks

| Callback | Description |
|----------|-------------|
| `onFileLeave` | Submit leave application |
| `onApproveLeave` | Approve leave request |
| `onRejectLeave` | Reject leave request |
| `onCancelLeave` | Cancel pending/approved leave |
| `onViewLeaveDetail` | Open leave application detail |
| `onAdjustBalance` | HR balance adjustment |
| `onCreateLeaveType` | Create new leave type |

## Philippine Statutory Leaves

- **Service Incentive Leave (SIL)** — 5 days after 1 year service
- **Maternity Leave** — 105 days (extended), 120 days for solo parents
- **Paternity Leave** — 7 days
- **Solo Parent Leave** — 7 days
- **VAWC Leave** — 10 days
- **Special Leave for Women** — 60 days

## Files to Reference

- `product-plan/sections/leave-management/tests.md`
- `product-plan/sections/leave-management/components/`
- `product-plan/sections/leave-management/types.ts`
- `product-plan/sections/leave-management/sample-data.json`

---

# Milestone 6: Self-Service Portal

## Goal

Implement web interface enabling employees and managers to independently manage their HR needs.

## Overview

This section provides self-service capabilities for employees to view payslips, file leave, check DTR, and for managers to approve requests and monitor their team.

**Key Functionality:**
- Employee dashboard with summary cards
- Payslip viewer with history and PDF download
- Leave filing with balance display
- DTR viewing with correction requests
- Manager approval queue for team requests

## Components

- `Dashboard` — Summary cards with quick actions
- `PayslipViewer` — Payslip list with detail view and PDF download
- `DTRView` — Calendar/list view of attendance with corrections
- `LeaveManagement` — Balance cards and application list
- `ApprovalQueue` — Manager view for pending team requests

## Callbacks

| Callback | Description |
|----------|-------------|
| `onViewPayslip` | Open payslip detail |
| `onDownloadPayslip` | Download payslip PDF |
| `onFileLeave` | Submit leave application |
| `onCancelLeave` | Cancel pending leave |
| `onViewDTR` | Open DTR detail modal |
| `onRequestCorrection` | Submit DTR correction |
| `onApproveRequest` | Approve team request |
| `onRejectRequest` | Reject team request |

## Role-Based Views

**Employee View:**
- Personal dashboard with own data
- File requests (leave, overtime, DTR correction)
- View own payslips and attendance

**Manager View:**
- Everything in employee view, plus:
- Approval queue for direct reports
- Team attendance summary

## Files to Reference

- `product-plan/sections/self-service-portal/tests.md`
- `product-plan/sections/self-service-portal/components/`
- `product-plan/sections/self-service-portal/types.ts`
- `product-plan/sections/self-service-portal/sample-data.json`
