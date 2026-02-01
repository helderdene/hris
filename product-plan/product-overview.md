# KasamaHR — Product Overview

## Summary

A multi-tenant SaaS platform for complete human resource lifecycle management, designed specifically for the Philippine market. Targeting mid-market to enterprise organizations in manufacturing, construction, and government sectors, KasamaHR provides full compliance with Philippine labor laws and government regulatory requirements including BIR, SSS, PhilHealth, and Pag-IBIG integrations.

## Key Features

- Employee Information Management (digital 201 file)
- Time & Attendance with MQTT-based facial recognition devices
- Payroll processing with Philippine tax compliance
- Leave management with statutory leave types (SIL, Maternity, Paternity, Solo Parent, VAWC)
- Recruitment & Onboarding workflows
- Performance Management cycles
- Training & Development tracking
- Employee Self-Service Portal
- Multi-tenant architecture with subdomain-based routing
- Mobile application support

## Planned Sections

1. **Employee Management** — Core employee records, digital 201 file, organization structure, and department/position management.

2. **Time & Attendance** — Work schedules, MQTT-based facial recognition integration, attendance logging, and Daily Time Record (DTR) processing.

3. **Payroll & Compliance** — Payroll computation engine, government deductions (SSS, PhilHealth, Pag-IBIG, BIR), and regulatory report generation.

4. **Leave Management** — Leave types configuration, balance tracking, application workflows, and statutory leave compliance.

5. **Self-Service Portal** — Employee and manager self-service features including payslips, leave filing, DTR viewing, and document requests.

## Data Model

The system manages these core entities:

- **Tenant** — Multi-tenant company/organization
- **Employee** — Central entity (digital 201 file) with personal info, government IDs, employment status
- **Department** — Hierarchical organizational units
- **Position** — Job titles with salary grades
- **EmployeeAssignment** — Links employees to positions, departments, supervisors
- **WorkSchedule** — Shift patterns and work hour definitions
- **AttendanceLog** — Raw biometric events from devices
- **DailyTimeRecord** — Summarized daily attendance
- **PayrollPeriod** — Payroll cycle definitions
- **PayrollRecord** — Computed pay for employees
- **LeaveType** — Leave category configurations
- **LeaveBalance** — Employee leave credits
- **LeaveApplication** — Leave requests and approvals
- **Loan** — Employee loan tracking
- **GovernmentReport** — Compliance report generation

## Design System

**Colors:**
- Primary: `blue` — Used for buttons, links, key accents
- Secondary: `emerald` — Used for tags, highlights, success states
- Neutral: `slate` — Used for backgrounds, text, borders

**Typography:**
- Heading: DM Sans
- Body: DM Sans
- Mono: JetBrains Mono

## Implementation Sequence

Build this product in milestones:

1. **Foundation** — Set up design tokens, data model types, routing structure, and application shell
2. **Employee Management** — Core employee records, 201 file, organization structure
3. **Time & Attendance** — Work schedules, biometric integration, DTR processing
4. **Payroll & Compliance** — Payroll computation, government deductions, regulatory reports
5. **Leave Management** — Leave types, balance tracking, approval workflows
6. **Self-Service Portal** — Employee/manager self-service features

Each milestone has a dedicated instruction document in `product-plan/instructions/`.
