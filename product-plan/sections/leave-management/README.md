# Leave Management

## Overview

A comprehensive leave management system compliant with Philippine labor laws, enabling employees to file leave requests, track balances, and receive approvals through a streamlined workflow. The system handles mandatory leave types (SIL, Maternity, Paternity, Solo Parent, VAWC, Special Leave for Women) with automatic validation, multi-level approval routing, and integration with time & attendance for accurate DTR marking.

## User Flows

- View leave dashboard with balances, pending requests, and team calendar
- Browse and understand available leave types with eligibility requirements
- View detailed leave balance breakdown by type with usage history
- File a leave application with date selection, type, and supporting documents
- Approve or reject leave requests as supervisor with comments
- Cancel a pending or approved leave request
- View team/company leave calendar with filtering options
- Adjust employee leave balances (HR admin) with audit trail
- Generate leave reports by department, type, or period

## Design Decisions

- Dashboard-first approach with balance cards and pending requests
- Leave type cards show eligibility and remaining balance
- Application form with calendar picker and real-time balance validation
- Team calendar for visibility planning

## Data Used

**Entities:**
- LeaveType — Leave category configuration with eligibility rules
- LeaveBalance — Employee credits per type per year
- LeaveApplication — Leave request with approval workflow

**From global model:** Employee, Tenant

## Visual Reference

See `screenshot.png` for the target UI design.

## Components Provided

- `LeaveDashboard` — Balance cards, pending requests, mini calendar
- `LeaveTypeList` — Leave type configuration cards
- `LeaveApprovalQueue` — Pending requests for supervisor approval
- `LeaveCalendar` — Team calendar with color-coded leave events

## Callback Props

| Callback | Description |
|----------|-------------|
| `onFileLeave` | Called when user submits leave application |
| `onApproveLeave` | Called when supervisor approves request |
| `onRejectLeave` | Called when supervisor rejects request |
| `onCancelLeave` | Called when user cancels leave |
| `onViewLeaveDetail` | Called when user views leave application |
| `onAdjustBalance` | Called when HR adjusts balance |
| `onCreateLeaveType` | Called when HR creates leave type |
| `onEditLeaveType` | Called when HR modifies leave type |
