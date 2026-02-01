# Milestone 3: Time & Attendance

> **Provide alongside:** `product-overview.md`
> **Prerequisites:** Milestone 1 (Foundation) and Milestone 2 (Employee Management) complete

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

Implement the Time & Attendance section — comprehensive time tracking with real-time MQTT-based biometric integration and automated attendance processing.

## Overview

This section manages work schedules, processes raw attendance logs from facial recognition devices, generates Daily Time Records (DTR), and handles overtime management with Philippine labor law compliance.

**Key Functionality:**
- Real-time attendance monitoring dashboard
- Work schedule configuration (fixed, flexible, shifting, compressed)
- Raw attendance log viewing with device info and captured photos
- DTR generation with computed late, undertime, overtime, night differential
- DTR correction request and approval workflow
- Overtime request pre-approval
- Biometric device management

## Recommended Approach: Test-Driven Development

Before implementing this section, **write tests first** based on the test specifications provided.

See `product-plan/sections/time-and-attendance/tests.md` for detailed test-writing instructions including:
- Key user flows to test (success and failure paths)
- Specific UI elements, button labels, and interactions to verify
- Expected behaviors and assertions

**TDD Workflow:**
1. Read `tests.md` and write failing tests for the key user flows
2. Implement the feature to make tests pass
3. Refactor while keeping tests green

## What to Implement

### Components

Copy the section components from `product-plan/sections/time-and-attendance/components/`:

- `AttendanceDashboard` — Real-time KPIs and recent logs feed
- `DailyTimeRecordList` — DTR table with employee attendance summaries
- `WorkScheduleList` — Schedule configuration list

### Data Layer

The components expect these data shapes (see `types.ts`):

- `WorkSchedule` — Shift patterns, time in/out, break times, grace periods
- `AttendanceLog` — Raw biometric events with timestamp, device, confidence
- `DailyTimeRecord` — Computed daily attendance (late, undertime, OT, night diff)
- `BiometricDevice` — MQTT device registration and status

You'll need to:
- Implement MQTT subscriber for real-time attendance logs
- Create DTR computation engine (late, undertime, OT, night diff)
- Handle Philippine labor law rules for overtime rates

### Callbacks

Wire up these user actions:

| Callback | Description |
|----------|-------------|
| `onViewDTR` | Open DTR detail view |
| `onRequestCorrection` | Submit DTR correction request |
| `onApproveCorrection` | Approve correction request |
| `onCreateSchedule` | Create new work schedule |
| `onEditSchedule` | Modify work schedule |
| `onAssignSchedule` | Bulk assign schedule to employees |
| `onExportDTR` | Download DTR report |

### Empty States

Implement empty state UI for when no records exist yet:

- **No attendance logs:** Show "No attendance logs yet" with device setup guidance
- **No DTR records:** Show "No DTR records for this period"
- **No work schedules:** Show "No schedules configured" with create prompt

### Real-Time Updates

The attendance dashboard should update in real-time:
- Implement WebSocket connection for live attendance feed
- Update present/absent counts as logs arrive
- Show recent clock events as they happen

## Files to Reference

- `product-plan/sections/time-and-attendance/README.md` — Feature overview
- `product-plan/sections/time-and-attendance/tests.md` — Test-writing instructions
- `product-plan/sections/time-and-attendance/components/` — React components
- `product-plan/sections/time-and-attendance/types.ts` — TypeScript interfaces
- `product-plan/sections/time-and-attendance/sample-data.json` — Test data
- `product-plan/sections/time-and-attendance/*.png` — Visual references

## Expected User Flows

### Flow 1: View Attendance Dashboard

1. User navigates to `/attendance`
2. User sees real-time KPIs (present today, late, absent, on leave)
3. User sees live feed of recent clock events
4. **Outcome:** Dashboard displays current attendance status

### Flow 2: Create Work Schedule

1. User clicks "Add Schedule" button
2. User enters schedule name and type (fixed, flexible, etc.)
3. User configures time in/out, break times, and grace period
4. User saves schedule
5. **Outcome:** New schedule appears in list, ready for assignment

### Flow 3: View Daily Time Record

1. User navigates to DTR list
2. User selects date range and employee filter
3. User clicks on an employee row
4. User sees DTR detail with time in/out, computed hours, breakdown
5. **Outcome:** Complete attendance record displayed

### Flow 4: Request DTR Correction

1. User opens DTR detail with missing/incorrect entry
2. User clicks "Request Correction"
3. User enters reason and supporting evidence
4. User submits request
5. **Outcome:** Correction request queued for supervisor approval

## Done When

- [ ] Tests written for key user flows (success and failure paths)
- [ ] All tests pass
- [ ] Attendance dashboard shows real-time data
- [ ] Work schedule CRUD operations work
- [ ] DTR list displays with filters
- [ ] DTR computation logic correct (late, undertime, OT, night diff)
- [ ] Correction request workflow functional
- [ ] Empty states display properly
- [ ] Matches the visual design
- [ ] Responsive on mobile
