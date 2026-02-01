# Test Instructions: Time & Attendance

These test-writing instructions are **framework-agnostic**. Adapt them to your testing setup (Jest, Vitest, Playwright, Cypress, React Testing Library, RSpec, Minitest, PHPUnit, etc.).

## Overview

Test the Time & Attendance section — comprehensive time tracking with biometric integration and automated DTR processing. Focus on schedule management, attendance viewing, DTR computation, and correction workflows.

---

## User Flow Tests

### Flow 1: View Attendance Dashboard

**Scenario:** User monitors real-time attendance status

#### Success Path

**Setup:**
- Employees have attendance records for today
- Some employees are late, on leave, or absent

**Steps:**
1. User navigates to `/attendance`
2. User sees attendance dashboard with KPI cards
3. User views recent attendance log feed

**Expected Results:**
- [ ] KPI cards show: "Present Today", "Late", "Absent", "On Leave"
- [ ] Numbers update in real-time as new logs arrive
- [ ] Recent logs feed shows latest clock events
- [ ] Each log shows: Employee name, Time, Status (In/Out), Device

---

### Flow 2: Create Work Schedule

**Scenario:** HR creates a new work schedule

#### Success Path

**Setup:**
- User has permission to manage schedules

**Steps:**
1. User clicks "Add Schedule" button
2. User enters schedule name: "Regular Day Shift"
3. User selects schedule type: "Fixed"
4. User sets Time In: 8:00 AM, Time Out: 5:00 PM
5. User sets Break: 12:00 PM - 1:00 PM
6. User sets Grace Period: 15 minutes
7. User clicks "Save"

**Expected Results:**
- [ ] Schedule form shows all configuration options
- [ ] Time picker allows selecting hours/minutes
- [ ] Success message: "Schedule created successfully"
- [ ] New schedule appears in schedule list

#### Failure Path: Validation Error

**Setup:**
- User submits schedule with Time Out before Time In

**Steps:**
1. User sets Time In: 5:00 PM
2. User sets Time Out: 8:00 AM
3. User clicks "Save"

**Expected Results:**
- [ ] Error: "Time Out must be after Time In"
- [ ] Form is not submitted

---

### Flow 3: View Daily Time Record

**Scenario:** User reviews employee DTR for a period

#### Success Path

**Setup:**
- DTR records exist for employees
- Records include various statuses (present, late, absent, leave)

**Steps:**
1. User navigates to DTR list
2. User selects date range (e.g., current month)
3. User clicks on an employee row
4. User sees DTR detail modal

**Expected Results:**
- [ ] DTR list shows: Employee, Date, Time In, Time Out, Hours Worked, Status
- [ ] Date range filter changes displayed records
- [ ] Detail modal shows: Computed hours, Late minutes, Undertime, OT, Night diff
- [ ] Status badges show correct colors (Present=green, Late=yellow, Absent=red)

---

### Flow 4: Request DTR Correction

**Scenario:** Employee requests correction for missing clock event

#### Success Path

**Setup:**
- Employee has a DTR entry with missing Time Out

**Steps:**
1. User opens DTR detail for the day
2. User clicks "Request Correction" button
3. User selects correction type: "Missing Time Out"
4. User enters actual time: 5:30 PM
5. User enters reason: "Forgot to clock out"
6. User clicks "Submit Request"

**Expected Results:**
- [ ] Correction form shows available correction types
- [ ] Time picker allows entering correct time
- [ ] Reason field is required
- [ ] Success message: "Correction request submitted"
- [ ] Request status shows "Pending Approval"

#### Failure Path: No Reason Provided

**Steps:**
1. User leaves reason field empty
2. User clicks "Submit Request"

**Expected Results:**
- [ ] Error: "Please provide a reason for the correction"
- [ ] Form is not submitted

---

## Empty State Tests

### Primary Empty State

**Scenario:** No attendance logs yet (new setup)

**Setup:**
- AttendanceLog list is empty (`[]`)
- No biometric devices registered

**Expected Results:**
- [ ] Dashboard shows "No attendance data yet"
- [ ] Shows guidance: "Connect biometric devices to start tracking"
- [ ] KPI cards show 0 values gracefully

### No DTR Records

**Scenario:** Selected period has no DTR records

**Setup:**
- Date range has no processed records

**Expected Results:**
- [ ] Shows "No DTR records for this period"
- [ ] Shows option to change date range

### No Work Schedules

**Scenario:** No schedules configured yet

**Setup:**
- WorkSchedule list is empty

**Expected Results:**
- [ ] Shows "No work schedules configured"
- [ ] Shows "Create Schedule" button prominently
- [ ] Explains that schedules are needed for DTR computation

---

## Component Interaction Tests

### AttendanceDashboard

**Renders correctly:**
- [ ] Shows "Present Today" count
- [ ] Shows "Late" count with threshold indicator
- [ ] Shows "Absent" count
- [ ] Shows "On Leave" count
- [ ] Recent logs feed displays latest events

**User interactions:**
- [ ] Clicking on KPI card could filter the view
- [ ] Real-time updates when new logs arrive (WebSocket)

### DailyTimeRecordList

**Renders correctly:**
- [ ] Table shows: Employee, Date, Time In, Time Out, Hours, Status
- [ ] Computed values displayed correctly
- [ ] Status badges with appropriate colors

**User interactions:**
- [ ] Clicking row opens DTR detail modal
- [ ] Date range filter changes displayed records
- [ ] Export button calls `onExportDTR`

### WorkScheduleList

**Renders correctly:**
- [ ] Lists all schedules with name and type
- [ ] Shows assigned employee count per schedule

**User interactions:**
- [ ] "Add Schedule" calls `onCreateSchedule`
- [ ] Clicking schedule row calls `onEditSchedule`
- [ ] "Assign" button calls `onAssignSchedule`

---

## Computation Tests

Test that DTR computation follows Philippine labor law:

### Late Computation
- [ ] Employee clocking in after scheduled time + grace period is marked late
- [ ] Late minutes calculated correctly

### Overtime Computation
- [ ] Regular OT: Work beyond 8 hours (1.25x)
- [ ] Rest day OT: Work on rest day (1.30x)
- [ ] Holiday OT: Work on holiday (premium rates)

### Night Differential
- [ ] 10% premium for work between 10:00 PM and 6:00 AM

---

## Edge Cases

- [ ] Handles multiple clock-ins/outs in a day
- [ ] Works across midnight shifts
- [ ] Handles missing clock-out gracefully
- [ ] Preserves filter state when navigating
- [ ] After creating first schedule, list updates correctly

---

## Accessibility Checks

- [ ] All interactive elements are keyboard accessible
- [ ] Time pickers have proper labels
- [ ] Table has proper header associations
- [ ] Status badges have aria-labels
- [ ] Real-time updates announced to screen readers

---

## Sample Test Data

```typescript
// Work schedule
const mockSchedule = {
  id: "sched-001",
  name: "Regular Day Shift",
  type: "fixed",
  timeIn: "08:00",
  timeOut: "17:00",
  breakStart: "12:00",
  breakEnd: "13:00",
  gracePeriodMinutes: 15
};

// Daily Time Record
const mockDTR = {
  id: "dtr-001",
  employeeId: "emp-001",
  employeeName: "Maria Santos",
  date: "2025-01-15",
  scheduledIn: "08:00",
  scheduledOut: "17:00",
  actualIn: "08:10",
  actualOut: "17:30",
  lateMinutes: 0, // Within grace
  undertimeMinutes: 0,
  overtimeMinutes: 30,
  nightDiffMinutes: 0,
  hoursWorked: 8.5,
  status: "present"
};

// Empty states
const mockEmptyDTRList = [];
const mockEmptyScheduleList = [];
```
