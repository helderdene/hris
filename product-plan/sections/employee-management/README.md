# Employee Management

## Overview

The Employee Management section serves as the central repository for all employee-related data, implementing the digital equivalent of the traditional 201 file. It manages employee master data, employment history, documents, and organization structure.

## User Flows

- View Employee Directory — Browse, search, filter, and sort employees with export to Excel/CSV
- View Employee Profile — Access comprehensive employee details in tabbed sections (personal info, employment, government IDs, contacts, documents)
- Add New Employee — Create employee record with personal info, government IDs, contact details, and initial assignment
- Edit Employee Information — Update employee data with full audit trail of changes
- Manage 201 File Documents — Upload, view, download, and organize employee documents (contracts, certifications, memos)
- View Organization Chart — Interactive hierarchical visualization of departments and reporting lines
- Manage Departments — Create, edit, and organize departments with hierarchy and department heads
- Manage Positions — Configure job positions with salary grades and department assignment

## Design Decisions

- Dashboard-first approach with KPI cards showing key metrics
- Employee list as paginated data table with column sorting and multi-filter
- Profile uses tabbed sections to organize 201 file information logically
- Document gallery with preview modal for quick viewing

## Data Used

**Entities:**
- Employee — Core employee record with personal info and government IDs
- Department — Organizational units with hierarchy
- Position — Job titles with salary grades
- EmployeeAssignment — Position/department assignments
- Document — 201 file attachments

**From global model:** Tenant, User, WorkLocation

## Visual Reference

See `screenshot.png` for the target UI design.

## Components Provided

- `EmployeeDashboard` — KPI cards with headcount, turnover, new hires metrics
- `EmployeeList` — Paginated table with search, filters, and sorting
- `EmployeeProfile` — Tabbed view with personal, employment, government, documents sections
- `StatCard` — Reusable stat card component

## Callback Props

| Callback | Description |
|----------|-------------|
| `onViewEmployee` | Called when user clicks to view employee profile |
| `onAddEmployee` | Called when user clicks to add new employee |
| `onEditEmployee` | Called when user clicks to edit employee |
| `onDeleteEmployee` | Called when user clicks to delete employee |
| `onExportEmployees` | Called when user clicks to export employee list |
| `onUploadDocument` | Called when user uploads a document |
| `onDownloadDocument` | Called when user downloads a document |
