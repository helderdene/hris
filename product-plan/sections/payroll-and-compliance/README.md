# Payroll & Compliance

## Overview

Full-featured payroll processing engine with Philippine tax and statutory compliance built-in. Handles payroll computation (basic pay, overtime, night differential, holiday pay), automatic government deductions (SSS, PhilHealth, Pag-IBIG, BIR withholding tax), and generation of all required regulatory reports for government remittances.

## User Flows

- View Payroll Dashboard — Overview of payroll periods, pending processing, and compliance status
- Manage Payroll Periods — Create and configure payroll periods (regular, supplemental, 13th month, final pay)
- Process Payroll — Run payroll computation for a period, review calculations, approve and finalize
- View Payroll Records — Browse individual employee payroll records with detailed breakdown
- Generate Payslips — Create and distribute digital payslips to employees
- Generate BIR Reports — Create Forms 1601-C, 1604-CF, 2316, and Alphalist in PDF/DAT format
- Generate SSS Reports — Create R3, R5, SBR, and ECL files for SSS remittance
- Generate PhilHealth Reports — Create ER2, RF1, and MDR files
- Generate Pag-IBIG Reports — Create MCRF and loan amortization schedules

## Design Decisions

- Dashboard shows payroll timeline with upcoming deadlines
- Period list uses status badges (draft, processing, approved, paid, closed)
- Payroll processing as step-by-step wizard with review phase
- Government reports hub centralizes all report generation

## Data Used

**Entities:**
- PayrollPeriod — Payroll cycle with dates, status, pay date
- PayrollRecord — Employee pay computation with breakdown
- PayrollDeduction — Individual deduction line items
- ContributionTable — SSS, PhilHealth, Pag-IBIG rate schedules
- TaxTable — BIR withholding tax brackets

**From global model:** Employee, Tenant, Loan

## Visual Reference

See `screenshot.png` for the target UI design.

## Components Provided

- `PayrollDashboard` — KPIs with total payroll, pending periods, deadlines
- `PayrollPeriodList` — Payroll cycle list with status indicators
- `PayrollRecordList` — Individual employee payroll records
- `GovernmentReportsHub` — Report type selection and generation

## Callback Props

| Callback | Description |
|----------|-------------|
| `onCreatePeriod` | Called when user creates payroll period |
| `onProcessPayroll` | Called when user runs payroll computation |
| `onApprovePeriod` | Called when user approves computed payroll |
| `onViewPayrollRecord` | Called when user views employee payroll |
| `onGeneratePayslip` | Called when user generates payslip |
| `onGenerateReport` | Called when user generates government report |
| `onExportReport` | Called when user downloads report file |
