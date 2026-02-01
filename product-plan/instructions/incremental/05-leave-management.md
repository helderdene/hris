# Milestone 5: Leave Management

> **Provide alongside:** `product-overview.md`
> **Prerequisites:** Milestones 1-4 complete (Foundation, Employee Management, Time & Attendance, Payroll & Compliance)

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

Implement the Leave Management section — a comprehensive leave management system compliant with Philippine labor laws.

## Overview

This section enables employees to file leave requests, track balances, and receive approvals through a streamlined workflow. It handles mandatory leave types (SIL, Maternity, Paternity, Solo Parent, VAWC, Special Leave for Women) with automatic validation, multi-level approval routing, and integration with time & attendance.

**Key Functionality:**
- Leave dashboard with balances and pending requests
- Leave type configuration with eligibility and accrual rules
- Leave application filing with date selection and documents
- Multi-level approval workflow
- Team/company leave calendar view
- Balance adjustments with audit trail
- Leave report generation

## Recommended Approach: Test-Driven Development

Before implementing this section, **write tests first** based on the test specifications provided.

See `product-plan/sections/leave-management/tests.md` for detailed test-writing instructions including:
- Key user flows to test (success and failure paths)
- Specific UI elements, button labels, and interactions to verify
- Expected behaviors and assertions

**TDD Workflow:**
1. Read `tests.md` and write failing tests for the key user flows
2. Implement the feature to make tests pass
3. Refactor while keeping tests green

## What to Implement

### Components

Copy the section components from `product-plan/sections/leave-management/components/`:

- `LeaveDashboard` — Balance cards, pending requests, mini calendar
- `LeaveTypeList` — Leave type configuration cards
- `LeaveApprovalQueue` — Pending requests for approval
- `LeaveCalendar` — Team calendar with color-coded leave

### Data Layer

The components expect these data shapes (see `types.ts`):

- `LeaveType` — Leave category configuration
- `LeaveBalance` — Employee credits per type
- `LeaveApplication` — Leave request with approval status

You'll need to:
- Implement leave balance tracking and accrual
- Create approval workflow engine
- Handle statutory leave validations
- Integrate with DTR for attendance marking

### Callbacks

Wire up these user actions:

| Callback | Description |
|----------|-------------|
| `onFileLeave` | Submit leave application |
| `onApproveLeave` | Approve leave request |
| `onRejectLeave` | Reject leave request |
| `onCancelLeave` | Cancel pending/approved leave |
| `onViewLeaveDetail` | Open leave application detail |
| `onAdjustBalance` | HR balance adjustment |
| `onCreateLeaveType` | Create new leave type |
| `onEditLeaveType` | Modify leave type |

### Empty States

Implement empty state UI for when no records exist yet:

- **No leave applications:** Show "No leave applications yet"
- **No pending approvals:** Show "All caught up! No pending approvals"
- **No leave types:** Show "No leave types configured" with setup guidance
- **No calendar events:** Show empty calendar state

### Philippine Statutory Leaves

Implement validation for mandatory leave types:

- **Service Incentive Leave (SIL)** — 5 days after 1 year service
- **Maternity Leave** — 105 days (extended), 120 days for solo parents
- **Paternity Leave** — 7 days
- **Solo Parent Leave** — 7 days
- **VAWC Leave** — 10 days
- **Special Leave for Women** — 60 days

## Files to Reference

- `product-plan/sections/leave-management/README.md` — Feature overview
- `product-plan/sections/leave-management/tests.md` — Test-writing instructions
- `product-plan/sections/leave-management/components/` — React components
- `product-plan/sections/leave-management/types.ts` — TypeScript interfaces
- `product-plan/sections/leave-management/sample-data.json` — Test data
- `product-plan/sections/leave-management/*.png` — Visual references

## Expected User Flows

### Flow 1: File Leave Application

1. User clicks "File Leave" button
2. User selects leave type from available types
3. User picks date range on calendar
4. User enters reason and attaches documents (if required)
5. User submits application
6. **Outcome:** Application pending approval, balance reserved

### Flow 2: Approve Leave Request

1. Supervisor sees pending request in approval queue
2. Supervisor reviews employee info, dates, and reason
3. Supervisor clicks "Approve" button
4. **Outcome:** Leave approved, employee notified, balance deducted

### Flow 3: View Leave Calendar

1. User navigates to Leave Calendar
2. User selects department or team filter
3. User sees color-coded leave events
4. User clicks on an event for details
5. **Outcome:** Team availability visualized

### Flow 4: Adjust Leave Balance (HR)

1. HR admin opens employee's leave balances
2. HR clicks "Adjust Balance" on a leave type
3. HR enters adjustment amount and reason
4. HR saves adjustment
5. **Outcome:** Balance updated with audit trail

## Done When

- [ ] Tests written for key user flows (success and failure paths)
- [ ] All tests pass
- [ ] Leave dashboard shows real balance data
- [ ] Leave type configuration works
- [ ] Leave filing with validation works
- [ ] Approval workflow functional
- [ ] Calendar displays leave events
- [ ] Balance tracking accurate
- [ ] Empty states display properly
- [ ] Matches the visual design
- [ ] Responsive on mobile
