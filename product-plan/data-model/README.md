# Data Model

## Overview

KasamaHR's data model is designed for multi-tenant Philippine HR management. All entities belong to a Tenant (company) for data isolation.

## Core Entities

### Organization

| Entity | Description |
|--------|-------------|
| **Tenant** | A company/organization using the platform. Has isolated data, settings, and branding. |
| **Department** | Organizational unit with optional hierarchy (parent departments). Has a designated head. |
| **Position** | Job title within a department. Includes salary grade and range information. |
| **WorkLocation** | Physical work site, branch, or office. |

### People

| Entity | Description |
|--------|-------------|
| **Employee** | Central entity (digital 201 file). Contains personal info, government IDs, employment status. |
| **User** | System login account. Can be linked to Employee for self-service access. |
| **EmployeeAssignment** | Links employee to position, department, location, and supervisor. Tracks history. |
| **Compensation** | Salary and payment details. Tracks basic pay, frequency, pay type, bank info. |
| **Document** | File attachment in 201 file. Contracts, certifications, memos with version tracking. |

### Time & Attendance

| Entity | Description |
|--------|-------------|
| **WorkSchedule** | Work hours and shift patterns. Supports fixed, flexible, shifting, compressed. |
| **AttendanceLog** | Raw biometric clock event from device. Timestamp, verification method, confidence. |
| **DailyTimeRecord** | Summarized daily attendance. Computed late, undertime, OT, night diff. |
| **BiometricDevice** | MQTT-enabled facial recognition device. Device credentials, location, status. |
| **Holiday** | Philippine holiday calendar entry. Regular, special, double holiday types. |

### Payroll

| Entity | Description |
|--------|-------------|
| **PayrollPeriod** | Payroll cycle (regular, supplemental, 13th month, final pay). Status tracking. |
| **PayrollRecord** | Employee's computed pay for a period. Gross, deductions, net breakdown. |
| **PayrollDeduction** | Individual deduction line item. Government, tax, loans, adjustments. |
| **ContributionTable** | Government contribution schedules for SSS, PhilHealth, Pag-IBIG. |
| **TaxTable** | BIR withholding tax brackets per TRAIN Law. |
| **Loan** | Employee loan record. SSS, Pag-IBIG, or company loans with amortization. |
| **GovernmentReport** | Generated compliance report. BIR, SSS, PhilHealth, Pag-IBIG formats. |

### Leave

| Entity | Description |
|--------|-------------|
| **LeaveType** | Leave category configuration. Paid, statutory, convertible, accrual rules. |
| **LeaveBalance** | Employee credits for a leave type and year. Brought forward, earned, used. |
| **LeaveApplication** | Leave request with date range, reason, approval status. |

## Key Relationships

```
Tenant
├── Employees
├── Departments
│   └── Positions
├── WorkSchedules
├── BiometricDevices
├── PayrollPeriods
│   └── PayrollRecords
│       └── PayrollDeductions
├── LeaveTypes
└── Holidays

Employee
├── EmployeeAssignments
│   ├── Position
│   ├── Department
│   └── Supervisor (Employee)
├── Compensation
├── Documents
├── AttendanceLogs
├── DailyTimeRecords
├── PayrollRecords
├── LeaveBalances
├── LeaveApplications
└── Loans
```

## Government ID Fields

Philippine-specific fields on Employee:

- **TIN** — Tax Identification Number (BIR)
- **SSS Number** — Social Security System
- **PhilHealth Number** — Philippine Health Insurance
- **Pag-IBIG Number** — Home Development Mutual Fund

## Multi-Tenancy

- All queries must filter by `tenantId`
- Subdomain-based tenant resolution (e.g., `acme.kasamahr.com`)
- Each tenant has isolated data, users, and configuration
- Shared infrastructure, separate logical databases

## Implementation Notes

- Use TypeScript interfaces from `types.ts` as your starting point
- Adapt to your database schema (SQL, NoSQL, etc.)
- Government contribution tables need regular updates
- Consider soft deletes for audit trail
