# Self-Service Portal

## Overview

A web interface enabling employees and managers to independently manage their HR needs. Employees can view payslips, file leave requests, check DTR, request documents, and apply for loans. Managers can additionally approve leave requests, view team attendance, and manage direct reports.

## User Flows

### Employee Features
- View and request updates to personal information
- Access digital payslips with download option
- File leave requests, view balances, track approval status
- View Daily Time Record (DTR) and request corrections
- File overtime requests
- Request documents (COE, ITR, clearance certificates)
- Apply for loans (SSS, Pag-IBIG, company loans)

### Manager Features
- View team's pending leave requests and approve/reject
- Monitor team attendance and DTR summaries
- View direct reports' profiles and information
- Approve overtime requests from team members

## Design Decisions

- Dashboard landing with quick action cards
- Mobile-first design for on-the-go access
- Payslip viewer with history timeline and PDF download
- DTR calendar view with day-by-day detail
- Manager approval queue with batch actions

## Data Used

**Entities:**
- Employee — Current user's employee record
- Payslip — Monthly payslips
- DailyTimeRecord — Attendance records
- LeaveBalance — Leave credits
- LeaveApplication — Leave requests
- PendingApproval — Items awaiting manager action
- TeamMember — Direct reports

**From global model:** Loan, DocumentRequest, OvertimeRequest

## Visual Reference

See `screenshot.png` for the target UI design.

## Components Provided

- `Dashboard` — Summary cards with quick actions and notifications
- `PayslipViewer` — Payslip list with detail view and PDF download
- `DTRView` — Calendar/list view of attendance with correction requests
- `LeaveManagement` — Balance cards and application list
- `ApprovalQueue` — Manager view for pending team requests

## Callback Props

| Callback | Description |
|----------|-------------|
| `onViewPayslip` | Called when user views payslip detail |
| `onDownloadPayslip` | Called when user downloads payslip PDF |
| `onFileLeave` | Called when user submits leave application |
| `onCancelLeave` | Called when user cancels pending leave |
| `onViewDTR` | Called when user views DTR detail |
| `onRequestCorrection` | Called when user requests DTR correction |
| `onApproveRequest` | Called when manager approves request |
| `onRejectRequest` | Called when manager rejects request |
| `onBatchApprove` | Called when manager batch approves requests |
| `onViewTeamMember` | Called when manager views team member profile |
