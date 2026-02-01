# Milestone 4: Payroll & Compliance

> **Provide alongside:** `product-overview.md`
> **Prerequisites:** Milestones 1-3 complete (Foundation, Employee Management, Time & Attendance)

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

## Goal

Implement the Payroll & Compliance section — full-featured payroll processing engine with Philippine tax and statutory compliance built-in.

## Overview

This section handles payroll computation (basic pay, overtime, night differential, holiday pay), automatic government deductions (SSS, PhilHealth, Pag-IBIG, BIR withholding tax), and generation of all required regulatory reports for government remittances.

**Key Functionality:**
- Payroll period management (regular, supplemental, 13th month, final pay)
- Automated payroll computation with earnings breakdown
- Government deduction calculations (SSS, PhilHealth, Pag-IBIG, BIR)
- Payslip generation with PDF export
- BIR forms generation (1601-C, 1604-CF, 2316, Alphalist)
- SSS, PhilHealth, Pag-IBIG report generation
- Contribution table management

## Recommended Approach: Test-Driven Development

Before implementing this section, **write tests first** based on the test specifications provided.

See `product-plan/sections/payroll-and-compliance/tests.md` for detailed test-writing instructions including:
- Key user flows to test (success and failure paths)
- Specific UI elements, button labels, and interactions to verify
- Expected behaviors and assertions

**TDD Workflow:**
1. Read `tests.md` and write failing tests for the key user flows
2. Implement the feature to make tests pass
3. Refactor while keeping tests green

## What to Implement

### Components

Copy the section components from `product-plan/sections/payroll-and-compliance/components/`:

- `PayrollDashboard` — KPIs with total payroll, pending periods, deadlines
- `PayrollPeriodList` — Payroll cycle list with status indicators
- `PayrollRecordList` — Individual employee payroll records
- `GovernmentReportsHub` — Report type selection and generation

### Data Layer

The components expect these data shapes (see `types.ts`):

- `PayrollPeriod` — Payroll cycle with dates, status, pay date
- `PayrollRecord` — Employee pay computation with breakdown
- `PayrollDeduction` — Individual deduction line items
- `ContributionTable` — SSS, PhilHealth, Pag-IBIG rate schedules
- `TaxTable` — BIR withholding tax brackets

You'll need to:
- Implement payroll computation engine
- Calculate government contributions per latest schedules
- Compute withholding tax per TRAIN Law
- Generate report files in required formats (PDF, DAT)

### Callbacks

Wire up these user actions:

| Callback | Description |
|----------|-------------|
| `onCreatePeriod` | Create new payroll period |
| `onProcessPayroll` | Run payroll computation |
| `onApprovePeriod` | Approve computed payroll |
| `onViewPayrollRecord` | Open employee payroll detail |
| `onGeneratePayslip` | Create employee payslip |
| `onGenerateReport` | Generate government report |
| `onExportReport` | Download report file |

### Empty States

Implement empty state UI for when no records exist yet:

- **No payroll periods:** Show "No payroll periods yet" with create prompt
- **No payroll records:** Show "No payroll records for this period"
- **No generated reports:** Show guidance on report generation

### Computation Logic

Implement Philippine-compliant payroll calculations:

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

- `product-plan/sections/payroll-and-compliance/README.md` — Feature overview
- `product-plan/sections/payroll-and-compliance/tests.md` — Test-writing instructions
- `product-plan/sections/payroll-and-compliance/components/` — React components
- `product-plan/sections/payroll-and-compliance/types.ts` — TypeScript interfaces
- `product-plan/sections/payroll-and-compliance/sample-data.json` — Test data
- `product-plan/sections/payroll-and-compliance/*.png` — Visual references

## Expected User Flows

### Flow 1: Create Payroll Period

1. User clicks "Create Period" button
2. User selects period type (regular, supplemental, 13th month)
3. User sets date range and pay date
4. User saves period
5. **Outcome:** New period appears in list as "Draft"

### Flow 2: Process Payroll

1. User opens a draft payroll period
2. User clicks "Process Payroll"
3. System computes earnings and deductions for all employees
4. User reviews computation results
5. User clicks "Approve"
6. **Outcome:** Period status changes to "Approved"

### Flow 3: Generate Payslip

1. User opens approved payroll period
2. User clicks on an employee's payroll record
3. User clicks "Generate Payslip"
4. System creates PDF payslip
5. **Outcome:** Payslip available for download

### Flow 4: Generate Government Report

1. User navigates to Government Reports Hub
2. User selects report type (e.g., BIR 1601-C)
3. User selects period/date range
4. User clicks "Generate"
5. **Outcome:** Report file ready for download

## Done When

- [ ] Tests written for key user flows (success and failure paths)
- [ ] All tests pass
- [ ] Payroll dashboard shows real KPI data
- [ ] Payroll period CRUD operations work
- [ ] Payroll computation produces correct results
- [ ] Government deductions calculated correctly
- [ ] Payslip generation works
- [ ] Government reports generate properly
- [ ] Empty states display properly
- [ ] Matches the visual design
- [ ] Responsive on mobile
