# Milestone 2: Employee Management

> **Provide alongside:** `product-overview.md`
> **Prerequisites:** Milestone 1 (Foundation) complete

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

Implement the Employee Management section — the central repository for all employee-related data, implementing the digital equivalent of the traditional 201 file.

## Overview

This section enables HR administrators to manage employee records, organizational structure, and employment documents. It serves as the foundation for all other HR modules.

**Key Functionality:**
- View employee directory with search, filter, and export capabilities
- Access comprehensive employee profiles with personal info, employment details, government IDs
- Add new employees with complete onboarding data
- Manage 201 file documents (contracts, certifications, memos)
- Visualize organization structure and reporting lines
- Configure departments and positions

## Recommended Approach: Test-Driven Development

Before implementing this section, **write tests first** based on the test specifications provided.

See `product-plan/sections/employee-management/tests.md` for detailed test-writing instructions including:
- Key user flows to test (success and failure paths)
- Specific UI elements, button labels, and interactions to verify
- Expected behaviors and assertions

**TDD Workflow:**
1. Read `tests.md` and write failing tests for the key user flows
2. Implement the feature to make tests pass
3. Refactor while keeping tests green

## What to Implement

### Components

Copy the section components from `product-plan/sections/employee-management/components/`:

- `EmployeeDashboard` — KPI cards with headcount, turnover, new hires
- `EmployeeList` — Paginated table with search, filters, sorting
- `EmployeeProfile` — Tabbed view with personal, employment, government, documents
- `StatCard` — Reusable stat card component

### Data Layer

The components expect these data shapes (see `types.ts`):

- `Employee` — Core employee record
- `Department` — Organizational units
- `Position` — Job titles with salary grades
- `EmployeeAssignment` — Position/department assignments
- `Document` — 201 file attachments

You'll need to:
- Create API endpoints for CRUD operations
- Implement search and filtering logic
- Handle file uploads for documents

### Callbacks

Wire up these user actions:

| Callback | Description |
|----------|-------------|
| `onViewEmployee` | Navigate to employee profile |
| `onAddEmployee` | Open create employee form |
| `onEditEmployee` | Open edit employee modal |
| `onDeleteEmployee` | Confirm and delete employee |
| `onExportEmployees` | Download CSV/Excel export |
| `onUploadDocument` | Upload 201 file document |
| `onDownloadDocument` | Download document file |

### Empty States

Implement empty state UI for when no records exist yet:

- **No employees:** Show "No employees yet" with "Add Employee" CTA
- **No search results:** Show "No employees match your filters" with clear filters option
- **No documents:** Show "No documents uploaded" with upload prompt

## Files to Reference

- `product-plan/sections/employee-management/README.md` — Feature overview
- `product-plan/sections/employee-management/tests.md` — Test-writing instructions
- `product-plan/sections/employee-management/components/` — React components
- `product-plan/sections/employee-management/types.ts` — TypeScript interfaces
- `product-plan/sections/employee-management/sample-data.json` — Test data
- `product-plan/sections/employee-management/*.png` — Visual references

## Expected User Flows

### Flow 1: View Employee Directory

1. User navigates to `/employees`
2. User sees Employee Dashboard with KPI cards (total employees, new hires, turnover)
3. User clicks "View All Employees" or navigates to employee list
4. User sees paginated employee table
5. **Outcome:** Employee list displays with search and filter options

### Flow 2: Add New Employee

1. User clicks "Add Employee" button
2. User fills in personal information (name, birthdate, gender, civil status)
3. User enters government IDs (TIN, SSS, PhilHealth, Pag-IBIG)
4. User assigns department, position, and supervisor
5. User clicks "Save"
6. **Outcome:** New employee appears in list, success message shown

### Flow 3: View Employee Profile

1. User clicks on employee row in the list
2. User sees employee profile with tabbed sections
3. User navigates between tabs (Personal, Employment, Government IDs, Documents)
4. **Outcome:** Complete 201 file information displayed

### Flow 4: Upload Document

1. User opens employee profile
2. User navigates to Documents tab
3. User clicks "Upload Document"
4. User selects file and document type
5. **Outcome:** Document appears in document list

## Done When

- [ ] Tests written for key user flows (success and failure paths)
- [ ] All tests pass
- [ ] Employee dashboard shows real KPI data
- [ ] Employee list with pagination, search, and filters
- [ ] Employee profile displays all information
- [ ] Add/edit employee forms work
- [ ] Document upload and download functional
- [ ] Empty states display properly
- [ ] Matches the visual design
- [ ] Responsive on mobile
