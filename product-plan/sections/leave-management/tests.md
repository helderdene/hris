# Test Instructions: Leave Management

These test-writing instructions are **framework-agnostic**. Adapt them to your testing setup (Jest, Vitest, Playwright, Cypress, React Testing Library, RSpec, Minitest, PHPUnit, etc.).

## Overview

Test the Leave Management section — comprehensive leave management compliant with Philippine labor laws. Focus on leave filing, balance tracking, approval workflows, and statutory leave compliance.

---

## User Flow Tests

### Flow 1: File Leave Application

**Scenario:** Employee files a leave request

#### Success Path

**Setup:**
- Employee has sufficient leave balance
- Leave types configured

**Steps:**
1. User clicks "File Leave" button
2. User selects leave type: "Vacation Leave"
3. User sees current balance for selected type
4. User selects dates: January 20-21, 2025 (2 days)
5. User enters reason: "Family vacation"
6. User clicks "Submit"

**Expected Results:**
- [ ] Leave type dropdown shows available types
- [ ] Balance displayed updates based on selected type
- [ ] Date picker allows range selection
- [ ] Days count calculated automatically
- [ ] Success message: "Leave application submitted"
- [ ] Balance shows "pending" deduction
- [ ] Application appears in pending list

#### Failure Path: Insufficient Balance

**Setup:**
- Employee has only 1 day balance
- Requests 2 days

**Steps:**
1. User selects dates spanning 2 days
2. User clicks "Submit"

**Expected Results:**
- [ ] Error: "Insufficient leave balance. Available: 1 day"
- [ ] Application is not submitted
- [ ] User can adjust dates

#### Failure Path: Overlapping Dates

**Setup:**
- Employee already has approved leave on selected dates

**Steps:**
1. User selects dates overlapping with existing leave

**Expected Results:**
- [ ] Error: "You already have leave on some of these dates"
- [ ] Shows conflicting dates
- [ ] Application is not submitted

---

### Flow 2: Approve Leave Request

**Scenario:** Supervisor approves team member's leave

#### Success Path

**Setup:**
- Pending leave request exists
- User is the supervisor

**Steps:**
1. User opens Approval Queue
2. User sees pending request with details
3. User reviews: Employee name, Leave type, Dates, Reason
4. User clicks "Approve"

**Expected Results:**
- [ ] Request details clearly visible
- [ ] Employee info and leave type displayed
- [ ] "Approve" and "Reject" buttons visible
- [ ] Success message: "Leave approved"
- [ ] Request removed from queue
- [ ] Employee balance deducted

### Flow 2b: Reject Leave Request

**Steps:**
1. User clicks "Reject"
2. User enters reason: "Critical project deadline"
3. User confirms rejection

**Expected Results:**
- [ ] Reason field is required
- [ ] Success message: "Leave rejected"
- [ ] Employee balance restored (if was reserved)
- [ ] Employee notified with rejection reason

---

### Flow 3: View Leave Calendar

**Scenario:** User views team leave calendar

#### Success Path

**Setup:**
- Team members have various approved leaves

**Steps:**
1. User navigates to Leave Calendar
2. User selects month: February 2025
3. User sees calendar with leave events
4. User clicks on an event

**Expected Results:**
- [ ] Calendar shows current month by default
- [ ] Navigation arrows to change month
- [ ] Leave events color-coded by type
- [ ] Clicking event shows leave details
- [ ] Multiple leaves on same day stacked

---

### Flow 4: Adjust Leave Balance (HR)

**Scenario:** HR adjusts employee leave balance

#### Success Path

**Setup:**
- User has HR admin role
- Employee record exists

**Steps:**
1. User navigates to employee's leave balances
2. User clicks "Adjust Balance" on Vacation Leave
3. User enters adjustment: +3 days
4. User enters reason: "Carryover from previous year"
5. User clicks "Save"

**Expected Results:**
- [ ] Adjustment form shows current balance
- [ ] Can add or subtract days
- [ ] Reason field is required
- [ ] Success message: "Balance adjusted"
- [ ] New balance reflected immediately
- [ ] Audit trail created

---

## Empty State Tests

### Primary Empty State

**Scenario:** No leave applications yet

**Setup:**
- LeaveApplication list is empty (`[]`)

**Expected Results:**
- [ ] Shows "No leave applications yet"
- [ ] Shows "File Leave" button
- [ ] Dashboard shows balance cards with full balances

### No Pending Approvals

**Scenario:** Supervisor has no pending requests

**Setup:**
- User is supervisor
- No pending requests from team

**Expected Results:**
- [ ] Approval queue shows "All caught up!"
- [ ] Shows "No pending requests from your team"
- [ ] Queue is empty but not broken

### No Leave Types

**Scenario:** No leave types configured

**Setup:**
- LeaveType list is empty

**Expected Results:**
- [ ] Shows "No leave types configured"
- [ ] Shows "Configure Leave Types" for HR
- [ ] File Leave disabled with explanation

---

## Statutory Leave Tests

Test Philippine mandatory leave types:

### Service Incentive Leave (SIL)
- [ ] 5 days for employees with 1+ year service
- [ ] Not available for employees under 1 year

### Maternity Leave
- [ ] 105 days (extended maternity)
- [ ] 120 days for solo parents
- [ ] Requires supporting documents

### Paternity Leave
- [ ] 7 days for married male employees
- [ ] Requires birth certificate

### Solo Parent Leave
- [ ] 7 days for certified solo parents
- [ ] Requires solo parent ID

### VAWC Leave
- [ ] 10 days for VAWC victims
- [ ] Requires barangay certification

---

## Component Interaction Tests

### LeaveDashboard

**Renders correctly:**
- [ ] Balance cards for each leave type
- [ ] Balance shows: Used, Pending, Available
- [ ] Pending requests count badge
- [ ] Mini calendar widget

**User interactions:**
- [ ] "File Leave" calls `onFileLeave`
- [ ] Clicking balance card shows details
- [ ] Mini calendar highlights leave dates

### LeaveApprovalQueue

**Renders correctly:**
- [ ] Lists pending requests
- [ ] Each shows: Employee, Type, Dates, Reason
- [ ] Urgency indicator if dates are soon

**User interactions:**
- [ ] "Approve" calls `onApproveLeave`
- [ ] "Reject" opens reason modal then calls `onRejectLeave`
- [ ] Clicking request opens detail view

### LeaveCalendar

**Renders correctly:**
- [ ] Monthly calendar grid
- [ ] Leave events as colored blocks
- [ ] Legend showing leave type colors

**User interactions:**
- [ ] Navigation changes month
- [ ] Clicking event shows leave details
- [ ] Filter by leave type or employee

---

## Edge Cases

- [ ] Handles half-day leave requests
- [ ] Works with consecutive days spanning weekends
- [ ] Handles leave that starts in one month, ends in another
- [ ] Cancelled leave restores balance correctly
- [ ] Balance carryover at year-end
- [ ] Pro-rated balance for mid-year hires

---

## Accessibility Checks

- [ ] All interactive elements are keyboard accessible
- [ ] Date picker is keyboard navigable
- [ ] Calendar events announced to screen readers
- [ ] Status badges have aria-labels
- [ ] Form validation errors announced

---

## Sample Test Data

```typescript
// Leave Type
const mockLeaveType = {
  id: "lt-001",
  code: "VL",
  name: "Vacation Leave",
  description: "Annual vacation leave",
  isPaid: true,
  isStatutory: false,
  defaultCredits: 15,
  maxCarryover: 5
};

// Leave Balance
const mockLeaveBalance = {
  id: "lb-001",
  employeeId: "emp-001",
  leaveTypeId: "lt-001",
  year: 2025,
  entitlement: 15,
  carryover: 3,
  used: 2,
  pending: 1,
  available: 15 // entitlement + carryover - used - pending
};

// Leave Application
const mockLeaveApplication = {
  id: "la-001",
  employeeId: "emp-001",
  employeeName: "Maria Santos",
  leaveTypeId: "lt-001",
  leaveTypeName: "Vacation Leave",
  startDate: "2025-01-20",
  endDate: "2025-01-21",
  days: 2,
  reason: "Family vacation",
  status: "pending",
  filedDate: "2025-01-15"
};

// Empty states
const mockEmptyLeaveApplications = [];
const mockNoPendingApprovals = [];
```
