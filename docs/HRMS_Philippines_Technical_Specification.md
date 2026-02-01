# Human Resource Management System
## Technical Specification Document
### Philippine Market Edition

**Prepared by:** HDSystem  
**Version:** 1.0  
**Date:** January 5, 2025

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture](#2-system-architecture)
3. [Database Schema](#3-database-schema)
4. [Module Specifications](#4-module-specifications)
5. [Philippine Regulatory Compliance](#5-philippine-regulatory-compliance)
6. [Biometric Integration](#6-biometric-integration)
7. [API Design](#7-api-design)
8. [Security & Data Protection](#8-security--data-protection)
9. [Implementation Roadmap](#9-implementation-roadmap)
10. [Technical Requirements](#10-technical-requirements)

---

## 1. Executive Summary

This document presents the comprehensive technical specification for a Human Resource Management System (HRMS) specifically designed for the Philippine market. The system targets mid-market to enterprise-level organizations in the manufacturing, construction, and government sectors.

The HRMS is architected as a multi-tenant SaaS platform built on Laravel and Vue.js, offering complete human resource lifecycle management with full compliance to Philippine labor laws and government regulatory requirements including BIR, SSS, PhilHealth, and Pag-IBIG integrations.

### 1.1 Key Objectives

1. Streamline HR operations from recruitment to retirement
2. Ensure 100% compliance with Philippine labor regulations
3. Automate payroll processing with accurate government deductions
4. Enable real-time workforce analytics and reporting
5. Provide seamless biometric integration via MQTT-enabled facial recognition devices

### 1.2 Target Industries

- **Manufacturing** - Shift scheduling, overtime management, compliance tracking
- **Construction** - Project-based workforce, field deployment, safety certifications
- **Government** - Plantilla management, service records, leave credit systems

---

## 2. System Architecture

### 2.1 Technology Stack

| Layer | Technology |
|-------|------------|
| Backend Framework | Laravel 11.x (PHP 8.3+) |
| Frontend Framework | Vue.js 3.x with Composition API, Pinia, Vue Router |
| Database | MySQL 8.0 / PostgreSQL 16 |
| Cache & Queue | Redis 7.x |
| Search Engine | Meilisearch / Elasticsearch |
| Real-time | Laravel Reverb (WebSocket), MQTT (Eclipse Mosquitto) |
| File Storage | S3-compatible (AWS S3, MinIO, DigitalOcean Spaces) |
| PDF Generation | DomPDF / Browsershot |
| API Documentation | Scramble / L5-Swagger |

### 2.2 Multi-Tenant Architecture

The system implements a hybrid multi-tenancy approach optimized for data isolation and scalability:

- **Database Strategy:** Shared database with tenant_id column discrimination for standard tenants; dedicated databases available for enterprise clients requiring strict data isolation
- **Tenant Identification:** Subdomain-based routing (company.hrms.ph) with fallback to header-based identification for API access
- **Data Isolation:** Global scopes automatically applied to all tenant-specific models ensuring complete data segregation
- **Configuration:** Per-tenant configuration for branding, feature flags, and integration credentials

### 2.3 High-Level Architecture Diagram

The system follows a layered architecture with clear separation of concerns:

```
Client Layer → API Gateway → Application Layer → Service Layer → Data Layer → External Integrations
```

- **Client Layer:** Vue.js SPA, Mobile App (Flutter/React Native), Biometric Devices
- **API Gateway:** Laravel with Sanctum authentication, rate limiting, request validation
- **Application Layer:** Controllers, Form Requests, Resources, Policies
- **Service Layer:** Business logic services, DTOs, Action classes
- **Data Layer:** Eloquent models, Repositories, Query builders
- **External:** BIR eFPS, SSS/PhilHealth/Pag-IBIG portals, MQTT broker, Email/SMS gateways

---

## 3. Database Schema

### 3.1 Core Tables

#### 3.1.1 Tenants Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| name | VARCHAR(255) | No | Company name |
| slug | VARCHAR(100) | No | URL-friendly identifier (subdomain) |
| domain | VARCHAR(255) | Yes | Custom domain if applicable |
| database_connection | VARCHAR(100) | Yes | Dedicated DB connection name |
| settings | JSON | Yes | Tenant-specific configurations |
| subscription_plan | ENUM | No | starter, professional, enterprise |
| subscription_ends_at | TIMESTAMP | Yes | Subscription expiry date |
| is_active | BOOLEAN | No | Tenant active status |
| created_at | TIMESTAMP | No | Record creation timestamp |
| updated_at | TIMESTAMP | No | Record update timestamp |

#### 3.1.2 Employees Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| tenant_id | ULID | No | Foreign key to tenants |
| employee_number | VARCHAR(50) | No | Unique employee ID per tenant |
| user_id | ULID | Yes | Link to users table for login |
| first_name | VARCHAR(100) | No | First name |
| middle_name | VARCHAR(100) | Yes | Middle name |
| last_name | VARCHAR(100) | No | Last name |
| suffix | VARCHAR(20) | Yes | Jr., Sr., III, etc. |
| birth_date | DATE | No | Date of birth |
| gender | ENUM | No | male, female |
| civil_status | ENUM | No | single, married, widowed, separated, divorced |
| nationality | VARCHAR(50) | No | Citizenship |
| photo_path | VARCHAR(500) | Yes | Profile photo storage path |
| tin | VARCHAR(20) | Yes | Tax Identification Number |
| sss_number | VARCHAR(20) | Yes | SSS Number |
| philhealth_number | VARCHAR(20) | Yes | PhilHealth ID Number |
| pagibig_number | VARCHAR(20) | Yes | Pag-IBIG MID Number |
| employment_status | ENUM | No | regular, probationary, contractual, project_based, seasonal, casual |
| date_hired | DATE | No | Employment start date |
| regularization_date | DATE | Yes | Date of regularization |
| separation_date | DATE | Yes | Employment end date |
| separation_reason | ENUM | Yes | resignation, termination, retirement, end_of_contract, death, awol |
| is_active | BOOLEAN | No | Active employment flag |

#### 3.1.3 Employee Contacts Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| employee_id | ULID | No | Foreign key to employees |
| address_type | ENUM | No | present, permanent |
| street_address | VARCHAR(500) | No | Street, building, unit |
| barangay | VARCHAR(100) | No | Barangay name |
| city_municipality | VARCHAR(100) | No | City or Municipality |
| province | VARCHAR(100) | No | Province |
| region | VARCHAR(100) | No | Region |
| zip_code | VARCHAR(10) | No | Postal code |
| mobile_number | VARCHAR(20) | Yes | Mobile phone (+63) |
| telephone_number | VARCHAR(20) | Yes | Landline number |
| email | VARCHAR(255) | Yes | Personal email address |

#### 3.1.4 Departments Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| tenant_id | ULID | No | Foreign key to tenants |
| parent_id | ULID | Yes | Self-referencing for hierarchy |
| code | VARCHAR(20) | No | Department code |
| name | VARCHAR(255) | No | Department name |
| head_employee_id | ULID | Yes | Department head |
| cost_center | VARCHAR(50) | Yes | Cost center code |
| is_active | BOOLEAN | No | Active status |

#### 3.1.5 Positions Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| tenant_id | ULID | No | Foreign key to tenants |
| department_id | ULID | No | Foreign key to departments |
| code | VARCHAR(20) | No | Position code |
| title | VARCHAR(255) | No | Position title |
| level | INT | No | Hierarchy level (1=highest) |
| salary_grade | VARCHAR(20) | Yes | Salary grade reference |
| min_salary | DECIMAL(15,2) | Yes | Minimum salary range |
| max_salary | DECIMAL(15,2) | Yes | Maximum salary range |
| is_active | BOOLEAN | No | Active status |

#### 3.1.6 Employee Assignments Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| employee_id | ULID | No | Foreign key to employees |
| position_id | ULID | No | Foreign key to positions |
| department_id | ULID | No | Foreign key to departments |
| work_location_id | ULID | Yes | Foreign key to work_locations |
| supervisor_id | ULID | Yes | Direct supervisor employee_id |
| effective_date | DATE | No | Assignment start date |
| end_date | DATE | Yes | Assignment end date |
| is_current | BOOLEAN | No | Current assignment flag |
| remarks | TEXT | Yes | Assignment notes |

### 3.2 Payroll Tables

#### 3.2.1 Compensation Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| employee_id | ULID | No | Foreign key to employees |
| basic_salary | DECIMAL(15,2) | No | Monthly basic salary |
| pay_frequency | ENUM | No | monthly, semi_monthly, weekly, daily |
| pay_type | ENUM | No | fixed, hourly, daily, piece_rate |
| hourly_rate | DECIMAL(10,2) | Yes | Computed hourly rate |
| daily_rate | DECIMAL(10,2) | Yes | Computed daily rate |
| bank_name | VARCHAR(100) | Yes | Payroll bank name |
| bank_account_number | VARCHAR(50) | Yes | Bank account number |
| effective_date | DATE | No | Compensation effective date |
| is_current | BOOLEAN | No | Current compensation flag |

#### 3.2.2 Payroll Periods Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| tenant_id | ULID | No | Foreign key to tenants |
| period_code | VARCHAR(20) | No | Period identifier (2024-01-A) |
| period_type | ENUM | No | regular, supplemental, 13th_month, final |
| start_date | DATE | No | Period start date |
| end_date | DATE | No | Period end date |
| cutoff_date | DATE | No | Attendance cutoff date |
| pay_date | DATE | No | Scheduled pay date |
| status | ENUM | No | draft, processing, approved, paid, closed |
| processed_by | ULID | Yes | User who processed payroll |
| processed_at | TIMESTAMP | Yes | Processing timestamp |
| approved_by | ULID | Yes | User who approved payroll |
| approved_at | TIMESTAMP | Yes | Approval timestamp |

#### 3.2.3 Payroll Records Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| payroll_period_id | ULID | No | Foreign key to payroll_periods |
| employee_id | ULID | No | Foreign key to employees |
| basic_pay | DECIMAL(15,2) | No | Basic pay for period |
| days_worked | DECIMAL(5,2) | No | Days worked in period |
| hours_worked | DECIMAL(7,2) | No | Regular hours worked |
| overtime_hours | DECIMAL(7,2) | No | Overtime hours |
| overtime_pay | DECIMAL(15,2) | No | Overtime pay |
| night_diff_hours | DECIMAL(7,2) | No | Night differential hours |
| night_diff_pay | DECIMAL(15,2) | No | Night differential pay |
| holiday_pay | DECIMAL(15,2) | No | Holiday pay |
| rest_day_pay | DECIMAL(15,2) | No | Rest day premium |
| gross_pay | DECIMAL(15,2) | No | Total gross pay |
| total_deductions | DECIMAL(15,2) | No | Total deductions |
| net_pay | DECIMAL(15,2) | No | Net pay |
| computation_json | JSON | Yes | Detailed computation breakdown |

#### 3.2.4 Payroll Deductions Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| payroll_record_id | ULID | No | Foreign key to payroll_records |
| deduction_type | ENUM | No | sss, philhealth, pagibig, withholding_tax, loan, other |
| deduction_code | VARCHAR(50) | No | Specific deduction identifier |
| description | VARCHAR(255) | Yes | Deduction description |
| amount | DECIMAL(15,2) | No | Deduction amount |
| employee_share | DECIMAL(15,2) | No | Employee share (for govt) |
| employer_share | DECIMAL(15,2) | No | Employer share (for govt) |

### 3.3 Time & Attendance Tables

#### 3.3.1 Work Schedules Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| tenant_id | ULID | No | Foreign key to tenants |
| code | VARCHAR(20) | No | Schedule code |
| name | VARCHAR(100) | No | Schedule name |
| schedule_type | ENUM | No | fixed, flexible, shifting, compressed |
| work_hours_per_day | DECIMAL(4,2) | No | Standard work hours per day |
| work_days_per_week | INT | No | Work days per week |
| grace_period_minutes | INT | No | Grace period in minutes |
| is_active | BOOLEAN | No | Active status |

#### 3.3.2 Schedule Details Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| work_schedule_id | ULID | No | Foreign key to work_schedules |
| day_of_week | TINYINT | No | 0=Sunday to 6=Saturday |
| is_rest_day | BOOLEAN | No | Rest day flag |
| time_in | TIME | Yes | Expected time in |
| time_out | TIME | Yes | Expected time out |
| break_start | TIME | Yes | Break start time |
| break_end | TIME | Yes | Break end time |
| break_minutes | INT | No | Total break duration |

#### 3.3.3 Attendance Logs Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| tenant_id | ULID | No | Foreign key to tenants |
| employee_id | ULID | No | Foreign key to employees |
| device_id | ULID | Yes | Foreign key to biometric_devices |
| log_datetime | DATETIME | No | Actual log timestamp |
| log_type | ENUM | No | time_in, time_out, break_out, break_in |
| source | ENUM | No | device, manual, mobile, web |
| verification_method | ENUM | Yes | face, fingerprint, card, pin |
| confidence_score | DECIMAL(5,2) | Yes | Biometric confidence % |
| photo_path | VARCHAR(500) | Yes | Captured photo path |
| latitude | DECIMAL(10,8) | Yes | GPS latitude |
| longitude | DECIMAL(11,8) | Yes | GPS longitude |
| raw_payload | JSON | Yes | Original device payload |

#### 3.3.4 Daily Time Records Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| employee_id | ULID | No | Foreign key to employees |
| work_date | DATE | No | Work date |
| schedule_id | ULID | Yes | Applied schedule |
| expected_time_in | TIME | Yes | Expected time in |
| expected_time_out | TIME | Yes | Expected time out |
| actual_time_in | DATETIME | Yes | Actual time in |
| actual_time_out | DATETIME | Yes | Actual time out |
| late_minutes | INT | No | Minutes late |
| undertime_minutes | INT | No | Minutes undertime |
| overtime_minutes | INT | No | OT minutes |
| night_diff_minutes | INT | No | Night diff minutes (10PM-6AM) |
| hours_worked | DECIMAL(5,2) | No | Total hours worked |
| day_type | ENUM | No | regular, rest_day, regular_holiday, special_holiday, double_holiday |
| status | ENUM | No | present, absent, leave, holiday, rest_day |
| remarks | TEXT | Yes | DTR remarks |

### 3.4 Leave Management Tables

#### 3.4.1 Leave Types Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| tenant_id | ULID | No | Foreign key to tenants |
| code | VARCHAR(20) | No | Leave type code (VL, SL, SIL) |
| name | VARCHAR(100) | No | Leave type name |
| description | TEXT | Yes | Detailed description |
| is_paid | BOOLEAN | No | Paid leave flag |
| is_statutory | BOOLEAN | No | Mandated by law |
| default_credits | DECIMAL(5,2) | Yes | Default annual credits |
| accrual_type | ENUM | No | annual, monthly, per_service_year, one_time |
| is_convertible | BOOLEAN | No | Can convert to cash |
| is_cumulative | BOOLEAN | No | Can carry over to next year |
| max_cumulative | DECIMAL(5,2) | Yes | Maximum cumulative credits |
| requires_attachment | BOOLEAN | No | Requires supporting docs |
| min_days_notice | INT | No | Minimum advance filing days |
| gender_restriction | ENUM | Yes | male, female, null=both |
| is_active | BOOLEAN | No | Active status |

#### 3.4.2 Leave Balances Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| employee_id | ULID | No | Foreign key to employees |
| leave_type_id | ULID | No | Foreign key to leave_types |
| year | YEAR | No | Leave year |
| brought_forward | DECIMAL(5,2) | No | Carried over from previous year |
| earned | DECIMAL(5,2) | No | Credits earned this year |
| adjustment | DECIMAL(5,2) | No | Manual adjustments |
| used | DECIMAL(5,2) | No | Credits used |
| pending | DECIMAL(5,2) | No | Pending approval |
| available | DECIMAL(5,2) | No | Available balance (computed) |
| forfeited | DECIMAL(5,2) | No | Expired credits |

#### 3.4.3 Leave Applications Table

| Column | Type | Nullable | Description |
|--------|------|----------|-------------|
| id | ULID | No | Primary key |
| tenant_id | ULID | No | Foreign key to tenants |
| employee_id | ULID | No | Foreign key to employees |
| leave_type_id | ULID | No | Foreign key to leave_types |
| application_number | VARCHAR(50) | No | Unique application number |
| start_date | DATE | No | Leave start date |
| end_date | DATE | No | Leave end date |
| days_applied | DECIMAL(5,2) | No | Number of days applied |
| half_day_type | ENUM | Yes | am, pm, null=full_day |
| reason | TEXT | Yes | Leave reason |
| status | ENUM | No | pending, approved, rejected, cancelled |
| approved_by | ULID | Yes | Approver employee_id |
| approved_at | TIMESTAMP | Yes | Approval timestamp |
| rejection_reason | TEXT | Yes | Reason for rejection |

---

## 4. Module Specifications

### 4.1 Employee Information Management

The Employee Information Management module serves as the central repository for all employee-related data, implementing the digital equivalent of the traditional 201 file.

#### 4.1.1 Features

- **Employee Master Data:** Complete personal information, government IDs, contact details, emergency contacts
- **Employment History:** Position changes, department transfers, salary adjustments with full audit trail
- **Document Management:** 201 file attachments (contracts, certifications, memos) with version control
- **Organization Structure:** Visual org chart, reporting hierarchies, department management
- **Employee Search:** Advanced filtering by multiple criteria with export capabilities
- **Bulk Operations:** Mass updates, imports via Excel/CSV, data validation

#### 4.1.2 Key Screens

1. Employee Dashboard: Summary view with KPIs (headcount, turnover, tenure distribution)
2. Employee Profile: Comprehensive single-page view with tabbed sections
3. Employee List: Paginated, sortable, filterable employee directory
4. Organization Chart: Interactive hierarchical visualization
5. 201 File Viewer: Document gallery with preview and download

### 4.2 Time & Attendance

Comprehensive time tracking with real-time biometric integration and automated attendance processing.

#### 4.2.1 Biometric Integration Architecture

The system integrates with facial recognition devices via MQTT protocol for real-time attendance logging:

- **MQTT Broker:** Eclipse Mosquitto with TLS encryption
- **Topic Structure:** `{tenant_id}/devices/{device_id}/attendance`
- **Payload Format:** JSON with employee_id, timestamp, verification_method, confidence_score, photo_base64
- **Laravel Integration:** MQTT listener service (Supervisor-managed) processing messages in real-time
- **Failover:** Local device storage with sync on reconnection

#### 4.2.2 MQTT Message Flow

```
Device → MQTT Broker → Laravel MQTT Subscriber → Queue Job → Attendance Log → DTR Processing → WebSocket Notification
```

#### 4.2.3 Scheduling Features

- **Fixed Schedules:** Standard 8-hour workday with defined time in/out
- **Flexible Schedules:** Core hours with flexible start/end times
- **Shifting Schedules:** Rotating shift patterns for manufacturing
- **Compressed Workweek:** 4-day or alternative arrangements
- **Overtime Management:** Pre-approval workflow, automatic computation

### 4.3 Payroll Processing

Full-featured payroll engine with Philippine tax and statutory compliance built-in.

#### 4.3.1 Payroll Computation Engine

- **Basic Pay Calculation:** Pro-rated for partial periods, daily/hourly rate computation
- **Overtime Rates:** Regular OT (125%), Rest Day OT (130%), Holiday OT (varying rates per DOLE)
- **Night Differential:** 10% premium for work between 10PM-6AM
- **Holiday Pay:** Automatic computation based on DOLE holiday calendar
- **Allowances:** Tax-exempt (de minimis) and taxable allowances
- **13th Month Pay:** Automatic accrual and computation

#### 4.3.2 Government Deductions

**SSS Contributions (2025 Schedule):**
- Monthly Salary Credit range: ₱4,250 to ₱35,000
- Employee Share: 4.5% of MSC
- Employer Share: 9.5% of MSC
- EC Contribution: Employer-paid, ₱10-₱30 based on MSC

**PhilHealth Contributions (2025):**
- Premium Rate: 5% of basic monthly salary
- Income Floor: ₱10,000
- Income Ceiling: ₱100,000
- Equal sharing between employee and employer

**Pag-IBIG Contributions:**
- Employee: 1% (≤₱1,500) or 2% (>₱1,500) of basic salary
- Employer: 2% of basic salary
- Maximum Monthly Compensation: ₱10,000

**Withholding Tax (BIR):**
- TRAIN Law tax table implementation
- Annual taxable income computation with graduated rates
- Non-taxable compensation exclusions (de minimis benefits)

### 4.4 Leave Management

Comprehensive leave tracking compliant with Philippine labor laws and company policies.

#### 4.4.1 Statutory Leave Types

| Leave Type | Days | Paid | Conditions |
|------------|------|------|------------|
| Service Incentive Leave | 5 | Yes | After 1 year of service |
| Maternity Leave | 105 | Yes | Live childbirth (SSS pays) |
| Paternity Leave | 7 | Yes | Married male employees |
| Solo Parent Leave | 7 | Yes | With Solo Parent ID |
| VAWC Leave | 10 | Yes | Women victims of abuse |
| Special Leave (Gynecological) | 60 | Yes | Post-surgery recovery |
| Bereavement Leave | 3-7 | Varies | Death of immediate family |

#### 4.4.2 Leave Workflow

1. Employee files leave request via ESS portal or mobile app
2. System validates leave balance and filing requirements
3. Request routed to immediate supervisor for approval
4. Optional: HR review for certain leave types
5. Notification sent upon approval/rejection
6. Leave balance automatically updated
7. DTR marked accordingly on leave dates

### 4.5 Recruitment & Onboarding

End-to-end applicant tracking and streamlined onboarding process.

#### 4.5.1 Recruitment Features

- **Job Requisition:** Department-initiated requests with budget approval workflow
- **Job Posting:** Multi-channel publishing (careers page, job boards integration)
- **Application Management:** Resume parsing, candidate database, duplicate detection
- **Interview Scheduling:** Calendar integration, panel coordination, video interview links
- **Assessment Tracking:** Skills tests, background checks, reference verification
- **Offer Management:** Template-based offer letters, e-signature integration

#### 4.5.2 Onboarding Workflow

- **Pre-boarding:** Document submission portal, pre-employment requirements checklist
- **Day 1 Tasks:** Account provisioning, equipment assignment, orientation scheduling
- **Training Assignment:** Mandatory training modules, department-specific programs
- **Probationary Tracking:** Performance milestones, evaluation schedules

### 4.6 Performance Management

Structured performance evaluation system with configurable KPIs and competencies.

#### 4.6.1 Performance Cycles

- **Annual Review:** Full performance evaluation with goal setting
- **Mid-Year Review:** Progress check and goal adjustment
- **Probationary Evaluation:** 3rd and 5th month assessments
- **Project-Based Review:** Post-project performance feedback

#### 4.6.2 Evaluation Components

- **KPI Achievement:** Quantitative metrics with targets and weights
- **Competency Assessment:** Behavioral competencies rated on scale
- **360-Degree Feedback:** Multi-rater evaluation (optional)
- **Self-Assessment:** Employee's own performance rating
- **Development Plan:** Training needs and career path discussion

### 4.7 Training & Development

Learning management system with certification tracking and compliance training.

#### 4.7.1 Training Features

- **Course Catalog:** Internal and external training programs
- **Training Calendar:** Scheduled sessions with enrollment management
- **Certification Tracking:** License renewals, expiry alerts
- **Compliance Training:** Mandatory safety and regulatory courses
- **Training History:** Complete records per employee
- **Cost Tracking:** Training expenses and ROI analysis

### 4.8 Employee Self-Service Portal

Web and mobile interface for employees to manage their HR needs independently.

#### 4.8.1 ESS Features

- **Personal Information:** View and request updates to personal data
- **Payslip Access:** Digital payslips with download option
- **Leave Management:** File requests, view balances, track approvals
- **Time & Attendance:** View DTR, request corrections, file overtime
- **Document Requests:** COE, ITR, clearance certificates
- **Loan Applications:** SSS, Pag-IBIG, company loans
- **Performance:** Goal setting, self-assessment, view evaluations

---

## 5. Philippine Regulatory Compliance

### 5.1 BIR Integration

#### 5.1.1 Withholding Tax Reports

| Form | Description | Frequency |
|------|-------------|-----------|
| BIR Form 1601-C | Monthly Remittance Return | Monthly, due 10th of following month |
| BIR Form 1604-CF | Annual Information Return | Annually, due January 31 |
| BIR Form 2316 | Certificate of Compensation | Annually, issued to employees |
| Alphalist | List of Employees | Attached to 1604-CF |

#### 5.1.2 System-Generated BIR Reports

- Automatic generation of all required BIR forms in PDF and DAT format
- Alphalist generation with employee data validation
- eFPS-compatible file generation for online filing
- Year-end adjustment processing for over/under withholding
- Substituted filing support for multiple employers

### 5.2 SSS Integration

#### 5.2.1 SSS Reports

- **R3 Report:** Monthly Contribution Collection List
- **R5 Report:** Quarterly Loan Amortization
- **SBR Generation:** SSS Billing Reference file for online remittance
- **Electronic Collection List (ECL)** for bank remittance

### 5.3 PhilHealth Integration

#### 5.3.1 PhilHealth Reports

- **ER2 Report:** Employer Remittance Report
- **RF1 Report:** Electronic Remittance Form
- **Member Data Record (MDR)** for new registrations
- Premium remittance file generation for e-payment

### 5.4 Pag-IBIG Integration

#### 5.4.1 Pag-IBIG Reports

- **MCRF** (Monthly Contribution Remittance Form)
- **STL** (Short Term Loan) amortization schedules
- **HDL** (Housing Loan) payment tracking
- Electronic submission file for Virtual Pag-IBIG

---

## 6. Biometric Integration

### 6.1 MQTT Architecture

The system uses MQTT protocol for real-time communication with facial recognition devices, providing reliable, low-latency attendance logging.

#### 6.1.1 MQTT Configuration

| Parameter | Value |
|-----------|-------|
| Broker | Eclipse Mosquitto 2.x |
| Port | 8883 (TLS), 1883 (non-TLS for internal) |
| Authentication | Username/Password + Client Certificate |
| QoS Level | QoS 1 (At least once delivery) |
| Keep Alive | 60 seconds |
| Clean Session | false (persistent sessions) |

#### 6.1.2 Topic Structure

Topic naming convention follows a hierarchical structure for organized message routing:

- **Attendance:** `hrms/{tenant_id}/devices/{device_id}/attendance`
- **Device Status:** `hrms/{tenant_id}/devices/{device_id}/status`
- **Commands:** `hrms/{tenant_id}/devices/{device_id}/command`
- **Sync:** `hrms/{tenant_id}/devices/{device_id}/sync`

#### 6.1.3 Attendance Payload Schema

```json
{
  "device_id": "string",
  "employee_id": "string",
  "timestamp": "ISO8601",
  "event_type": "time_in|time_out|break_out|break_in",
  "verification_method": "face|fingerprint|card|pin",
  "confidence_score": 0.00-100.00,
  "photo_base64": "string|null",
  "temperature": 0.0,
  "mask_detected": true|false
}
```

### 6.2 Laravel MQTT Integration

The system uses php-mqtt/laravel-client package for MQTT operations with a dedicated Supervisor-managed worker process.

#### 6.2.1 Processing Flow

1. MQTT Subscriber receives message from broker
2. Message validated against JSON schema
3. ProcessAttendanceLog job dispatched to queue
4. Job creates AttendanceLog record
5. DTR record updated/created
6. WebSocket event broadcast to dashboard
7. Photo saved to S3 if present

---

## 7. API Design

### 7.1 API Standards

- REST API following JSON:API specification
- Authentication: Laravel Sanctum (SPA) / Personal Access Tokens (API)
- Versioning: URL-based (api/v1/)
- Rate Limiting: 60 requests/minute (configurable per tenant)
- Pagination: Cursor-based for large datasets
- Filtering: Query parameter based with operators

### 7.2 Core API Endpoints

#### 7.2.1 Employee Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/employees | List employees with filters |
| POST | /api/v1/employees | Create new employee |
| GET | /api/v1/employees/{id} | Get employee details |
| PUT | /api/v1/employees/{id} | Update employee |
| DELETE | /api/v1/employees/{id} | Soft delete employee |
| GET | /api/v1/employees/{id}/documents | List 201 file documents |
| POST | /api/v1/employees/{id}/documents | Upload document |

#### 7.2.2 Attendance Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/attendance/logs | List attendance logs |
| POST | /api/v1/attendance/logs | Manual log entry |
| GET | /api/v1/attendance/dtr | Get DTR records |
| GET | /api/v1/attendance/dtr/{employee_id} | Employee DTR summary |
| POST | /api/v1/attendance/corrections | Request DTR correction |

#### 7.2.3 Payroll Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/payroll/periods | List payroll periods |
| POST | /api/v1/payroll/periods/{id}/process | Process payroll |
| GET | /api/v1/payroll/records | List payroll records |
| GET | /api/v1/payroll/payslips/{id} | Get payslip PDF |
| GET | /api/v1/payroll/reports/bir | Generate BIR reports |
| GET | /api/v1/payroll/reports/government | Generate SSS/PhilHealth/Pag-IBIG |

#### 7.2.4 Leave Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/leaves/types | List leave types |
| GET | /api/v1/leaves/balances | Get leave balances |
| POST | /api/v1/leaves/applications | File leave application |
| PUT | /api/v1/leaves/applications/{id}/approve | Approve leave |
| PUT | /api/v1/leaves/applications/{id}/reject | Reject leave |

---

## 8. Security & Data Protection

### 8.1 Authentication & Authorization

- **Multi-factor Authentication (MFA):** TOTP-based 2FA for sensitive operations
- **Role-Based Access Control (RBAC):** Granular permissions with role hierarchies
- **Session Management:** Concurrent session limits, idle timeout, forced logout
- **Password Policy:** Minimum complexity, rotation enforcement, breach detection
- **API Security:** Token-based authentication with expiration and refresh

### 8.2 Data Security

- **Encryption at Rest:** AES-256 for sensitive data (TIN, bank accounts, salaries)
- **Encryption in Transit:** TLS 1.3 for all communications
- **Data Masking:** Partial display of sensitive information in UI
- **Audit Logging:** Comprehensive activity logs with tamper protection
- **Backup & Recovery:** Automated daily backups with point-in-time recovery

### 8.3 Compliance

- **Data Privacy Act of 2012 (RA 10173):** Full compliance with Philippine data protection law
- **NPC Registration:** Support for Data Processing System registration requirements
- **Data Subject Rights:** Mechanisms for access, correction, erasure requests
- **Breach Notification:** Automated alerts and reporting workflows

---

## 9. Implementation Roadmap

### 9.1 Phase 1: Foundation (Months 1-3)

- Multi-tenant architecture setup
- Core database schema implementation
- Authentication and authorization system
- Employee Information Management module
- Organization structure management
- Basic Vue.js admin interface

### 9.2 Phase 2: Time & Payroll (Months 4-6)

- Work schedule management
- MQTT integration for biometric devices
- Attendance logging and DTR processing
- Payroll computation engine
- Government deductions (SSS, PhilHealth, Pag-IBIG, BIR)
- Payslip generation

### 9.3 Phase 3: Leave & Compliance (Months 7-8)

- Leave types and policies configuration
- Leave application workflow
- BIR report generation (1601-C, 1604-CF, 2316, Alphalist)
- SSS/PhilHealth/Pag-IBIG remittance file generation
- Holiday calendar management

### 9.4 Phase 4: HR Operations (Months 9-10)

- Recruitment module (job requisitions, applicant tracking)
- Onboarding workflow
- Performance management system
- Training and certification tracking

### 9.5 Phase 5: Self-Service & Mobile (Months 11-12)

- Employee Self-Service portal
- Manager Self-Service features
- Mobile application (Flutter/React Native)
- Push notifications
- Analytics and reporting dashboards
- System optimization and UAT

---

## 10. Technical Requirements

### 10.1 Server Requirements

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| CPU | 4 cores | 8+ cores |
| RAM | 8 GB | 32 GB |
| Storage | 100 GB SSD | 500 GB NVMe SSD |
| OS | Ubuntu 22.04 LTS | Ubuntu 24.04 LTS |
| PHP | 8.2 | 8.3+ |
| MySQL | 8.0 | 8.0 / PostgreSQL 16 |
| Redis | 6.0 | 7.x |
| Node.js | 18 LTS | 20 LTS |

### 10.2 Browser Support

- Chrome 90+ (recommended)
- Firefox 88+
- Safari 14+
- Edge 90+

### 10.3 Supported Biometric Devices

- ZKTeco (SpeedFace V5L, ProFace X, MB460)
- Hikvision (DS-K1T671M, DS-K1T642MF)
- Suprema (FaceStation F2, BioStation 2)
- Custom MQTT-enabled devices (with API documentation)

---

*End of Document*

*For questions or clarifications, please contact HDSystem*
