# Milestone 6: Self-Service Portal

> **Provide alongside:** `product-overview.md`
> **Prerequisites:** Milestones 1-5 complete (all previous sections)

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

Implement the Self-Service Portal — a web interface enabling employees and managers to independently manage their HR needs.

## Overview

This section provides self-service capabilities for employees to view payslips, file leave, check DTR, and request documents. Managers can additionally approve requests and monitor their team.

**Key Functionality:**
- Employee dashboard with summary cards
- Payslip viewer with history and PDF download
- Leave filing with balance display
- DTR viewing with correction requests
- Manager approval queue for team requests
- Team attendance overview
- Document request submission

## Recommended Approach: Test-Driven Development

Before implementing this section, **write tests first** based on the test specifications provided.

See `product-plan/sections/self-service-portal/tests.md` for detailed test-writing instructions including:
- Key user flows to test (success and failure paths)
- Specific UI elements, button labels, and interactions to verify
- Expected behaviors and assertions

**TDD Workflow:**
1. Read `tests.md` and write failing tests for the key user flows
2. Implement the feature to make tests pass
3. Refactor while keeping tests green

## What to Implement

### Components

Copy the section components from `product-plan/sections/self-service-portal/components/`:

- `Dashboard` — Summary cards with quick actions
- `PayslipViewer` — Payslip list with detail view and PDF download
- `DTRView` — Calendar/list view of attendance with corrections
- `LeaveManagement` — Balance cards and application list
- `ApprovalQueue` — Manager view for pending team requests

### Data Layer

The components expect these data shapes (see `types.ts`):

- `Employee` — Current user's employee record
- `Payslip` — Monthly payslips
- `DailyTimeRecord` — Attendance records
- `LeaveBalance` — Leave credits
- `LeaveApplication` — Leave requests
- `PendingApproval` — Items awaiting manager action
- `TeamMember` — Direct reports

You'll need to:
- Filter data by current user's employee ID
- Implement role-based access (employee vs manager views)
- Create PDF generation for payslips

### Callbacks

Wire up these user actions:

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
| `onViewTeamMember` | View direct report's profile |

### Empty States

Implement empty state UI for when no records exist yet:

- **No payslips:** Show "No payslips available yet"
- **No DTR records:** Show "No attendance records for this period"
- **No leave applications:** Show "No leave applications" with file leave CTA
- **No pending approvals:** Show "All caught up! No pending requests"
- **First-time user:** Guide user through available features

### Role-Based Views

**Employee View:**
- Personal dashboard with own data
- File requests (leave, overtime, DTR correction)
- View own payslips and attendance

**Manager View:**
- Everything in employee view, plus:
- Approval queue for direct reports
- Team attendance summary
- View direct reports' profiles

## Files to Reference

- `product-plan/sections/self-service-portal/README.md` — Feature overview
- `product-plan/sections/self-service-portal/tests.md` — Test-writing instructions
- `product-plan/sections/self-service-portal/components/` — React components
- `product-plan/sections/self-service-portal/types.ts` — TypeScript interfaces
- `product-plan/sections/self-service-portal/sample-data.json` — Test data
- `product-plan/sections/self-service-portal/*.png` — Visual references

## Expected User Flows

### Flow 1: View Payslip

1. Employee navigates to Self-Service Portal
2. Employee clicks "View Payslips" or navigates to payslip viewer
3. Employee sees list of monthly payslips
4. Employee clicks on a payslip to see detail
5. Employee clicks "Download PDF"
6. **Outcome:** Payslip PDF downloaded to device

### Flow 2: File Leave Request

1. Employee clicks "File Leave" on dashboard
2. Employee selects leave type and sees remaining balance
3. Employee picks dates on calendar
4. Employee enters reason
5. Employee submits
6. **Outcome:** Leave request submitted for approval

### Flow 3: View Daily Time Record

1. Employee navigates to DTR view
2. Employee sees current month calendar with attendance
3. Employee clicks on a day to see detail
4. Employee sees time in/out, late, OT breakdown
5. **Outcome:** Attendance record displayed

### Flow 4: Approve Team Request (Manager)

1. Manager sees notification of pending requests
2. Manager opens Approval Queue
3. Manager reviews request details (employee, type, dates, reason)
4. Manager clicks "Approve" or "Reject"
5. **Outcome:** Request processed, employee notified

## Done When

- [ ] Tests written for key user flows (success and failure paths)
- [ ] All tests pass
- [ ] Employee dashboard shows personal data
- [ ] Payslip viewer with history and download
- [ ] DTR view with calendar and detail modal
- [ ] Leave management with filing works
- [ ] Manager approval queue functional
- [ ] Role-based access working
- [ ] Empty states display properly
- [ ] Matches the visual design
- [ ] Responsive on mobile (critical for this section)
