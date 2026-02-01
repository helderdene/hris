# Test Instructions: Payroll & Compliance

These test-writing instructions are **framework-agnostic**. Adapt them to your testing setup (Jest, Vitest, Playwright, Cypress, React Testing Library, RSpec, Minitest, PHPUnit, etc.).

## Overview

Test the Payroll & Compliance section — full-featured payroll processing with Philippine tax compliance. Focus on payroll period management, computation accuracy, government deductions, and report generation.

---

## User Flow Tests

### Flow 1: Create Payroll Period

**Scenario:** HR creates a new payroll period

#### Success Path

**Setup:**
- User has permission to manage payroll
- No existing period for the selected dates

**Steps:**
1. User clicks "Create Period" button
2. User selects period type: "Regular"
3. User selects period: "January 1-15, 2025"
4. User sets pay date: "January 20, 2025"
5. User clicks "Create"

**Expected Results:**
- [ ] Period type options: Regular, Supplemental, 13th Month, Final Pay
- [ ] Date range picker shows valid options
- [ ] Success message: "Payroll period created"
- [ ] New period appears in list with "Draft" status

#### Failure Path: Overlapping Period

**Setup:**
- Period already exists for selected dates

**Steps:**
1. User selects dates overlapping with existing period

**Expected Results:**
- [ ] Error: "Period overlaps with existing payroll"
- [ ] Period is not created

---

### Flow 2: Process Payroll

**Scenario:** HR runs payroll computation

#### Success Path

**Setup:**
- Draft payroll period exists
- Employees have DTR records for the period

**Steps:**
1. User opens draft payroll period
2. User clicks "Process Payroll"
3. System shows processing progress
4. User sees computation results
5. User reviews employee payroll records
6. User clicks "Approve"

**Expected Results:**
- [ ] Processing shows progress indicator
- [ ] Results show: Total Gross, Total Deductions, Total Net
- [ ] Individual records show earnings and deductions breakdown
- [ ] Period status changes to "Approved"

#### Failure Path: Missing DTR Data

**Setup:**
- Some employees have no DTR records

**Steps:**
1. User clicks "Process Payroll"

**Expected Results:**
- [ ] Warning: "3 employees have incomplete DTR data"
- [ ] Shows list of affected employees
- [ ] User can proceed or fix data first

---

### Flow 3: View Payroll Record

**Scenario:** User views detailed employee payroll

#### Success Path

**Setup:**
- Processed payroll period exists

**Steps:**
1. User opens payroll period
2. User clicks on employee row
3. User sees payroll detail breakdown

**Expected Results:**
- [ ] Earnings section shows: Basic Pay, Overtime, Night Diff, Holiday Pay
- [ ] Deductions section shows: SSS, PhilHealth, Pag-IBIG, Tax, Loans
- [ ] Summary shows: Gross Pay, Total Deductions, Net Pay
- [ ] Calculations match expected values

---

### Flow 4: Generate Government Report

**Scenario:** HR generates BIR 1601-C report

#### Success Path

**Setup:**
- Approved payroll period exists

**Steps:**
1. User navigates to Government Reports Hub
2. User selects report type: "BIR 1601-C"
3. User selects period: "January 2025"
4. User clicks "Generate"
5. User clicks "Download"

**Expected Results:**
- [ ] Report types listed: BIR (1601-C, 1604-CF, 2316), SSS (R3, R5), PhilHealth (ER2), Pag-IBIG (MCRF)
- [ ] Period selection shows available periods
- [ ] Generation shows progress
- [ ] Download provides correct file format (PDF or DAT)

---

## Empty State Tests

### Primary Empty State

**Scenario:** No payroll periods exist yet

**Setup:**
- PayrollPeriod list is empty (`[]`)

**Expected Results:**
- [ ] Shows "No payroll periods yet"
- [ ] Shows "Create your first payroll period to get started"
- [ ] Shows "Create Period" button prominently
- [ ] Dashboard KPIs show zeros

### No Payroll Records

**Scenario:** Period exists but not yet processed

**Setup:**
- Draft period with no computed records

**Expected Results:**
- [ ] Shows "Payroll not yet processed"
- [ ] Shows "Process Payroll" button
- [ ] Empty records list

### No Generated Reports

**Scenario:** No reports generated for period

**Setup:**
- Processed period but no reports yet

**Expected Results:**
- [ ] Reports hub shows available report types
- [ ] Each report type shows "Not generated" or "Generate" button
- [ ] No downloaded files listed

---

## Computation Tests

Test that payroll computation follows Philippine regulations:

### Basic Pay Computation
- [ ] Monthly rate: Basic / 1
- [ ] Semi-monthly rate: Basic / 2
- [ ] Daily rate: Basic / working days
- [ ] Hourly rate: Daily / 8

### Government Deductions

**SSS Contribution:**
- [ ] Employee share computed from contribution table
- [ ] Employer share computed correctly
- [ ] Ceiling limits applied

**PhilHealth Contribution:**
- [ ] Current rate applied (e.g., 5% of basic, split 50/50)
- [ ] Minimum and maximum limits applied

**Pag-IBIG Contribution:**
- [ ] Employee share: 1-2% based on salary
- [ ] Employer share: 2%
- [ ] Maximum contribution cap

**Withholding Tax:**
- [ ] TRAIN Law brackets applied
- [ ] Tax computed on taxable income (after SSS, PhilHealth, Pag-IBIG)
- [ ] Tax-exempt employees handled correctly

### Overtime Rates
- [ ] Regular OT: 1.25x hourly rate
- [ ] Rest day OT: 1.30x hourly rate
- [ ] Regular holiday: 2.00x daily rate
- [ ] Special holiday: 1.30x daily rate

---

## Component Interaction Tests

### PayrollDashboard

**Renders correctly:**
- [ ] Shows "Total Payroll" amount for current period
- [ ] Shows "Pending Periods" count
- [ ] Shows "Upcoming Deadlines" list (pay dates, filing dates)

### PayrollPeriodList

**Renders correctly:**
- [ ] Lists periods with: Type, Date Range, Pay Date, Status
- [ ] Status badges: Draft (gray), Processing (yellow), Approved (green), Paid (blue)

**User interactions:**
- [ ] Clicking period opens detail view
- [ ] "Create Period" calls `onCreatePeriod`
- [ ] "Process" calls `onProcessPayroll`
- [ ] "Approve" calls `onApprovePeriod`

### GovernmentReportsHub

**Renders correctly:**
- [ ] BIR section: 1601-C, 1604-CF, 2316, Alphalist
- [ ] SSS section: R3, R5, SBR, ECL
- [ ] PhilHealth section: ER2, RF1
- [ ] Pag-IBIG section: MCRF

**User interactions:**
- [ ] Selecting report type shows generation form
- [ ] "Generate" calls `onGenerateReport`
- [ ] "Download" calls `onExportReport`

---

## Edge Cases

- [ ] Handles employee with multiple loan deductions
- [ ] Works with mid-month hires (prorated salary)
- [ ] Handles final pay computation (resigned employee)
- [ ] 13th month pay computed correctly
- [ ] Handles employees with different pay frequencies

---

## Accessibility Checks

- [ ] All interactive elements are keyboard accessible
- [ ] Form fields have proper labels
- [ ] Tables have proper header associations
- [ ] Status badges have aria-labels
- [ ] Progress indicators announced to screen readers

---

## Sample Test Data

```typescript
// Payroll Period
const mockPeriod = {
  id: "pp-001",
  type: "regular",
  startDate: "2025-01-01",
  endDate: "2025-01-15",
  payDate: "2025-01-20",
  status: "approved",
  totalGross: 500000.00,
  totalDeductions: 75000.00,
  totalNet: 425000.00
};

// Payroll Record
const mockPayrollRecord = {
  id: "pr-001",
  periodId: "pp-001",
  employeeId: "emp-001",
  employeeName: "Maria Santos",
  basicPay: 25000.00,
  overtime: 1500.00,
  nightDiff: 500.00,
  holidayPay: 0,
  grossPay: 27000.00,
  sssDeduction: 1125.00,
  philhealthDeduction: 675.00,
  pagibigDeduction: 100.00,
  taxDeduction: 2500.00,
  loanDeductions: 1000.00,
  totalDeductions: 5400.00,
  netPay: 21600.00
};

// Empty states
const mockEmptyPeriodList = [];
```
