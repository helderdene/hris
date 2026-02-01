# Test Instructions: Self-Service Portal

These test-writing instructions are **framework-agnostic**. Adapt them to your testing setup (Jest, Vitest, Playwright, Cypress, React Testing Library, RSpec, Minitest, PHPUnit, etc.).

## Overview

Test the Self-Service Portal — employee and manager self-service interface for HR tasks. Focus on payslip viewing, leave filing, DTR checking, and manager approval workflows.

---

## User Flow Tests

### Flow 1: View Payslip

**Scenario:** Employee views and downloads their payslip

#### Success Path

**Setup:**
- Employee has payslip records
- Multiple months available

**Steps:**
1. User navigates to Self-Service Portal dashboard
2. User clicks "View Payslips" or payslip widget
3. User sees list of monthly payslips
4. User clicks on a payslip (e.g., "December 2024")
5. User sees detailed breakdown
6. User clicks "Download PDF"

**Expected Results:**
- [ ] Payslip list shows: Month, Net Pay, Status (Paid)
- [ ] Detail shows: Gross Pay, Deductions breakdown, Net Pay
- [ ] Deductions itemized: SSS, PhilHealth, Pag-IBIG, Tax, Loans
- [ ] PDF download initiates file download
- [ ] Payslip branded with company logo

---

### Flow 2: File Leave Request

**Scenario:** Employee files a leave request from self-service

#### Success Path

**Setup:**
- Employee has leave balances
- Leave types configured

**Steps:**
1. User clicks "File Leave" on dashboard
2. User selects leave type from dropdown
3. User sees available balance
4. User picks dates on calendar
5. User enters reason
6. User clicks "Submit"

**Expected Results:**
- [ ] Balance displayed for selected leave type
- [ ] Calendar allows date range selection
- [ ] Days count calculated automatically
- [ ] Success message: "Leave request submitted for approval"
- [ ] Request appears in "My Requests" with "Pending" status

#### Failure Path: Insufficient Balance

**Steps:**
1. User selects more days than available balance

**Expected Results:**
- [ ] Warning shown: "Insufficient balance"
- [ ] Submit button disabled or shows explanation
- [ ] User can adjust dates

---

### Flow 3: View Daily Time Record

**Scenario:** Employee checks their attendance for the month

#### Success Path

**Setup:**
- Employee has DTR records for current month

**Steps:**
1. User navigates to "My DTR" or clicks DTR widget
2. User sees calendar view with January 2025
3. User clicks on a specific day (e.g., January 15)
4. User sees detail modal

**Expected Results:**
- [ ] Calendar shows current month by default
- [ ] Days color-coded: Present (green), Late (yellow), Absent (red), Leave (blue)
- [ ] Month navigation arrows work
- [ ] Day detail shows: Time In, Time Out, Hours Worked, Late minutes, OT

---

### Flow 4: Request DTR Correction

**Scenario:** Employee requests correction for missed clock-out

#### Success Path

**Setup:**
- DTR record exists with missing time out

**Steps:**
1. User views DTR detail for the day
2. User clicks "Request Correction"
3. User selects correction type: "Missing Time Out"
4. User enters actual time: 5:30 PM
5. User enters reason: "Forgot to clock out"
6. User clicks "Submit"

**Expected Results:**
- [ ] Correction form shows available correction types
- [ ] Time picker for entering correct time
- [ ] Reason field required
- [ ] Success message: "Correction request submitted"
- [ ] Request status shows "Pending Supervisor Approval"

---

### Flow 5: Manager Approval Queue

**Scenario:** Manager approves team leave requests

#### Success Path

**Setup:**
- User is a manager
- Team members have pending requests

**Steps:**
1. Manager opens dashboard
2. Manager sees "Pending Approvals" badge (e.g., "3 pending")
3. Manager clicks to open Approval Queue
4. Manager reviews request: Employee, Type, Dates, Reason
5. Manager clicks "Approve"

**Expected Results:**
- [ ] Badge shows pending count
- [ ] Queue lists all pending requests from direct reports
- [ ] Each request shows: Employee name/photo, Request type, Details
- [ ] "Approve" and "Reject" buttons visible
- [ ] Success message: "Request approved"
- [ ] Item removed from queue

### Flow 5b: Batch Approval

**Steps:**
1. Manager selects multiple requests via checkboxes
2. Manager clicks "Approve Selected"

**Expected Results:**
- [ ] Checkbox selection works
- [ ] "Approve Selected" button appears when items selected
- [ ] All selected requests approved
- [ ] Success message: "3 requests approved"

---

## Empty State Tests

### Primary Empty State

**Scenario:** New employee with no data yet

**Setup:**
- Employee just created, no payslips, no DTR

**Expected Results:**
- [ ] Dashboard shows helpful welcome message
- [ ] Payslips section: "No payslips available yet"
- [ ] DTR section: "No attendance records yet"
- [ ] Leave balances show initial credits (if configured)

### No Payslips

**Scenario:** Employee navigates to payslips but none exist

**Setup:**
- Payslip list is empty

**Expected Results:**
- [ ] Shows "No payslips available yet"
- [ ] Shows "Payslips will appear here after payroll processing"
- [ ] No broken UI

### No DTR Records

**Scenario:** Selected month has no DTR data

**Setup:**
- DTR records empty for selected period

**Expected Results:**
- [ ] Calendar shows month but days are empty/gray
- [ ] Shows "No attendance records for this period"
- [ ] Can still navigate to other months

### No Pending Approvals (Manager)

**Scenario:** Manager has no pending requests

**Setup:**
- User is manager
- No pending requests from team

**Expected Results:**
- [ ] Shows "All caught up!"
- [ ] Shows "No pending requests from your team"
- [ ] Badge shows 0 or not displayed

### No Leave Applications

**Scenario:** Employee has never filed leave

**Setup:**
- LeaveApplication list is empty

**Expected Results:**
- [ ] Shows "No leave applications yet"
- [ ] Shows "File Leave" button prominently
- [ ] Leave balances still visible

---

## Role-Based Tests

### Employee Role

- [ ] Can view own payslips
- [ ] Can view own DTR
- [ ] Can file leave requests
- [ ] Can request DTR corrections
- [ ] Cannot see manager approval queue
- [ ] Cannot approve/reject requests

### Manager Role

- [ ] Has all employee capabilities
- [ ] Can see "Pending Approvals" section
- [ ] Can approve/reject team requests
- [ ] Can view team DTR summary
- [ ] Can see direct reports list

---

## Component Interaction Tests

### Dashboard

**Renders correctly:**
- [ ] Quick action cards: View Payslip, File Leave, View DTR
- [ ] Leave balance summary
- [ ] Latest payslip preview
- [ ] Pending requests badge (if manager)

**User interactions:**
- [ ] Quick action cards navigate to respective sections
- [ ] Notification badges clickable

### PayslipViewer

**Renders correctly:**
- [ ] Year filter/dropdown
- [ ] List of payslips with month and amount
- [ ] Selected payslip shows full detail

**User interactions:**
- [ ] Clicking payslip opens detail
- [ ] "Download PDF" calls `onDownloadPayslip`
- [ ] Year filter changes displayed payslips

### DTRView

**Renders correctly:**
- [ ] Month/year header with navigation
- [ ] Calendar grid with status indicators
- [ ] Summary stats: Days Present, Hours Worked, Late, OT

**User interactions:**
- [ ] Clicking day opens detail modal
- [ ] "Request Correction" calls `onRequestCorrection`
- [ ] Calendar/List toggle switches view

### ApprovalQueue

**Renders correctly:**
- [ ] Filter by request type
- [ ] Request cards with employee info
- [ ] Action buttons per request

**User interactions:**
- [ ] Checkbox selection for batch actions
- [ ] "Approve" calls `onApproveRequest`
- [ ] "Reject" opens reason modal
- [ ] "Approve Selected" calls `onBatchApprove`

---

## Mobile Responsiveness Tests

This section is critical for mobile use:

- [ ] Dashboard cards stack vertically on mobile
- [ ] Navigation accessible via hamburger menu
- [ ] Payslip detail readable on small screen
- [ ] DTR calendar scrollable/zoomable
- [ ] Approval actions easy to tap
- [ ] Form inputs properly sized for touch

---

## Edge Cases

- [ ] Handles employee viewing their first-ever payslip
- [ ] DTR calendar works across month boundaries
- [ ] Leave balance updates in real-time after filing
- [ ] Manager sees only direct reports' requests
- [ ] Cancelled leave request restores pending balance

---

## Accessibility Checks

- [ ] All interactive elements keyboard accessible
- [ ] Calendar navigation via keyboard
- [ ] Screen reader announces status changes
- [ ] Touch targets at least 44x44px on mobile
- [ ] Color contrast meets WCAG standards

---

## Sample Test Data

```typescript
// Current Employee
const mockCurrentEmployee = {
  id: "emp-001",
  firstName: "Maria",
  lastName: "Dela Cruz",
  position: "Software Engineer",
  department: "Engineering",
  isManager: false
};

// Payslip
const mockPayslip = {
  id: "ps-001",
  period: "December 2024",
  periodStart: "2024-12-01",
  periodEnd: "2024-12-31",
  grossPay: 27000.00,
  deductions: {
    sss: 1125.00,
    philhealth: 675.00,
    pagibig: 100.00,
    tax: 2500.00,
    loans: 1000.00
  },
  totalDeductions: 5400.00,
  netPay: 21600.00
};

// Daily Time Record
const mockDTR = {
  id: "dtr-001",
  date: "2025-01-15",
  timeIn: "08:05",
  timeOut: "17:30",
  hoursWorked: 8.5,
  lateMinutes: 0,
  overtimeMinutes: 30,
  status: "present"
};

// Leave Balance
const mockLeaveBalance = {
  leaveType: "Vacation Leave",
  entitlement: 15,
  used: 2,
  pending: 1,
  available: 12
};

// Pending Approval (for manager)
const mockPendingApproval = {
  id: "la-001",
  type: "leave",
  employeeId: "emp-002",
  employeeName: "Juan Carlos",
  requestType: "Vacation Leave",
  details: "Feb 10-11, 2025 (2 days)",
  reason: "Family reunion",
  filedDate: "2025-01-19",
  urgency: "normal"
};

// Empty states
const mockNoPayslips = [];
const mockNoDTR = [];
const mockNoPendingApprovals = [];
```
