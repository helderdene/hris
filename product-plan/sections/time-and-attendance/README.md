# Time & Attendance

## Overview

Comprehensive time tracking with real-time MQTT-based biometric integration and automated attendance processing. This section manages work schedules, processes raw attendance logs from facial recognition devices, generates Daily Time Records (DTR), and handles overtime management with Philippine labor law compliance.

## User Flows

- View Attendance Dashboard — Real-time attendance monitoring with present/absent counts, late arrivals, and device status
- Manage Work Schedules — Create and configure schedule types (fixed, flexible, shifting, compressed) with time in/out, break times, and grace periods
- Assign Schedules to Employees — Bulk assign work schedules to employees or departments
- View Attendance Logs — Browse raw biometric logs with device info, verification method, confidence score
- View Daily Time Records (DTR) — Review processed attendance with computed late, undertime, overtime, and night differential
- Request DTR Correction — Submit correction requests for missing or incorrect logs
- Approve DTR Corrections — Supervisor/HR reviews and approves correction requests
- Export DTR Report — Generate DTR summaries by employee, department, or date range

## Design Decisions

- Real-time dashboard with live attendance feed using WebSocket updates
- Calendar and list view options for DTR browsing
- Work schedule configuration with visual day-by-day setup
- Computation of late, undertime, OT based on Philippine labor law

## Data Used

**Entities:**
- WorkSchedule — Shift patterns, time in/out, break times, grace periods
- AttendanceLog — Raw biometric events with timestamp, device, confidence
- DailyTimeRecord — Computed daily attendance (late, undertime, OT, night diff)
- BiometricDevice — MQTT device registration and status

**From global model:** Employee, Tenant

## Visual Reference

See `screenshot.png` for the target UI design.

## Components Provided

- `AttendanceDashboard` — Real-time KPIs and recent logs feed
- `DailyTimeRecordList` — DTR table with employee attendance summaries
- `WorkScheduleList` — Schedule configuration list with create/edit

## Callback Props

| Callback | Description |
|----------|-------------|
| `onViewDTR` | Called when user clicks to view DTR detail |
| `onRequestCorrection` | Called when user submits DTR correction |
| `onApproveCorrection` | Called when supervisor approves correction |
| `onCreateSchedule` | Called when user creates new schedule |
| `onEditSchedule` | Called when user modifies schedule |
| `onAssignSchedule` | Called when user assigns schedule to employees |
| `onExportDTR` | Called when user exports DTR report |
