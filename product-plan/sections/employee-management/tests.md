# Test Instructions: Employee Management

These test-writing instructions are **framework-agnostic**. Adapt them to your testing setup (Jest, Vitest, Playwright, Cypress, React Testing Library, RSpec, Minitest, PHPUnit, etc.).

## Overview

Test the Employee Management section — the central repository for all employee-related data (digital 201 file). Focus on employee directory browsing, profile viewing, employee creation, and document management.

---

## User Flow Tests

### Flow 1: View Employee Directory

**Scenario:** User browses and searches the employee list

#### Success Path

**Setup:**
- Employee data loaded (multiple employees with different departments, positions)
- User has permission to view employees

**Steps:**
1. User navigates to `/employees`
2. User sees Employee Dashboard with KPI cards
3. User clicks "View All" or navigates to employee list
4. User sees paginated employee table
5. User types search term in search field
6. User selects department from filter dropdown

**Expected Results:**
- [ ] Dashboard displays KPI cards: "Total Employees", "New Hires", "Turnover Rate"
- [ ] Employee table shows columns: Name, Employee ID, Department, Position, Status
- [ ] Search filters results as user types
- [ ] Department filter narrows down employee list
- [ ] Pagination controls appear when results exceed page size

#### Failure Path: No Search Results

**Setup:**
- Search term matches no employees

**Steps:**
1. User types "xyznonexistent" in search field

**Expected Results:**
- [ ] Shows "No employees match your filters" message
- [ ] Displays "Clear filters" option
- [ ] Table is empty but not broken

---

### Flow 2: Add New Employee

**Scenario:** HR creates a new employee record

#### Success Path

**Setup:**
- User has permission to create employees
- Departments and positions exist

**Steps:**
1. User clicks "Add Employee" button
2. User fills Personal Info tab: First Name, Last Name, Birthdate, Gender, Civil Status
3. User fills Government IDs: TIN, SSS, PhilHealth, Pag-IBIG
4. User selects Department and Position
5. User clicks "Save" button

**Expected Results:**
- [ ] Form validates required fields before submission
- [ ] Success toast: "Employee created successfully"
- [ ] New employee appears in employee list
- [ ] Form closes/resets after successful save

#### Failure Path: Validation Error

**Setup:**
- User submits form with missing required fields

**Steps:**
1. User clicks "Add Employee"
2. User leaves "First Name" empty
3. User clicks "Save"

**Expected Results:**
- [ ] Form shows error: "First name is required"
- [ ] Form is not submitted
- [ ] Focus moves to first invalid field

#### Failure Path: Duplicate Employee ID

**Setup:**
- Employee ID already exists in system

**Steps:**
1. User enters duplicate employee ID
2. User clicks "Save"

**Expected Results:**
- [ ] Error message: "Employee ID already exists"
- [ ] User can correct and retry

---

### Flow 3: View Employee Profile

**Scenario:** User views detailed employee information

#### Success Path

**Setup:**
- Employee exists with complete data

**Steps:**
1. User clicks on employee row in list
2. User sees profile page with employee header
3. User clicks "Employment" tab
4. User clicks "Government IDs" tab
5. User clicks "Documents" tab

**Expected Results:**
- [ ] Profile header shows: Name, Photo/Initials, Position, Department, Status badge
- [ ] "Personal" tab shows: Birthdate, Gender, Civil Status, Contact Info
- [ ] "Employment" tab shows: Employee ID, Hire Date, Department, Position, Supervisor
- [ ] "Government IDs" tab shows: TIN, SSS, PhilHealth, Pag-IBIG numbers
- [ ] "Documents" tab shows list of uploaded documents

---

### Flow 4: Upload Document

**Scenario:** User uploads a document to employee's 201 file

#### Success Path

**Setup:**
- User is viewing employee profile
- User has permission to upload documents

**Steps:**
1. User navigates to "Documents" tab
2. User clicks "Upload Document" button
3. User selects file from file picker
4. User selects document type (Contract, Certification, Memo)
5. User clicks "Upload" to confirm

**Expected Results:**
- [ ] File picker opens to select file
- [ ] Document type selection shows available types
- [ ] Progress indicator shows upload status
- [ ] Success message: "Document uploaded successfully"
- [ ] New document appears in document list

#### Failure Path: File Too Large

**Setup:**
- User selects file larger than allowed size

**Steps:**
1. User selects 50MB file

**Expected Results:**
- [ ] Error message: "File size exceeds limit (max 10MB)"
- [ ] Upload is prevented

---

## Empty State Tests

### Primary Empty State

**Scenario:** Company has no employees yet (first-time setup)

**Setup:**
- Employee list is empty (`[]`)

**Expected Results:**
- [ ] Shows heading "No employees yet"
- [ ] Shows description "Add your first employee to get started"
- [ ] Shows "Add Employee" button prominently
- [ ] Clicking button opens employee creation form
- [ ] Dashboard KPIs show zeros gracefully

### Documents Empty State

**Scenario:** Employee has no documents uploaded

**Setup:**
- Employee exists but has no documents

**Expected Results:**
- [ ] Documents tab shows "No documents uploaded"
- [ ] Shows "Upload Document" prompt
- [ ] Clear CTA to upload first document

### Filtered Empty State

**Scenario:** Search/filter returns no results

**Setup:**
- Employees exist but filter matches none

**Expected Results:**
- [ ] Shows "No employees match your filters"
- [ ] Shows "Clear filters" link
- [ ] Clicking clears filters and shows all employees

---

## Component Interaction Tests

### EmployeeDashboard

**Renders correctly:**
- [ ] Shows "Total Employees" stat card with count
- [ ] Shows "New Hires" stat card (this month count)
- [ ] Shows "Turnover Rate" stat card with percentage
- [ ] All stat cards have appropriate icons

**User interactions:**
- [ ] Clicking "View All Employees" navigates to employee list

### EmployeeList

**Renders correctly:**
- [ ] Table headers: Name, Employee ID, Department, Position, Status
- [ ] Each row shows employee data correctly
- [ ] Status badges show correct colors (Active=green, Inactive=gray)

**User interactions:**
- [ ] Clicking row calls `onViewEmployee` with employee id
- [ ] Clicking "Add Employee" calls `onAddEmployee`
- [ ] Search input filters results in real-time
- [ ] Export button calls `onExportEmployees`

### EmployeeProfile

**Renders correctly:**
- [ ] Profile header shows employee name and photo/initials
- [ ] All tabs are visible: Personal, Employment, Government IDs, Documents
- [ ] Active tab content displays correctly

**User interactions:**
- [ ] Clicking tab switches displayed content
- [ ] Edit button calls `onEditEmployee`
- [ ] Upload document calls `onUploadDocument`

---

## Edge Cases

- [ ] Handles employee with very long name (text truncation)
- [ ] Works with 1 employee and 1000+ employees (pagination)
- [ ] Preserves search/filter state when returning to list
- [ ] After creating first employee, list updates correctly
- [ ] After deleting last employee, empty state appears
- [ ] Profile loads gracefully with missing optional fields

---

## Accessibility Checks

- [ ] All interactive elements are keyboard accessible
- [ ] Form fields have associated labels
- [ ] Table has proper header associations
- [ ] Status badges have aria-labels
- [ ] Focus is managed when opening/closing modals

---

## Sample Test Data

```typescript
// Populated state
const mockEmployee = {
  id: "emp-001",
  firstName: "Maria",
  lastName: "Santos",
  email: "maria.santos@company.com",
  employeeId: "EMP-2024-0001",
  department: "Engineering",
  position: "Software Engineer",
  status: "active",
  hireDate: "2024-01-15",
  governmentIds: {
    tin: "123-456-789-000",
    sss: "12-3456789-0",
    philhealth: "01-234567890-1",
    pagibig: "1234-5678-9012"
  }
};

const mockEmployees = [mockEmployee, /* ... more */];

// Empty states
const mockEmptyEmployeeList = [];

const mockEmployeeNoDocuments = {
  ...mockEmployee,
  documents: []
};
```
