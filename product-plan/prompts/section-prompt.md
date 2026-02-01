# Section Implementation Prompt

Copy and paste this prompt into your coding agent to implement one section at a time.

---

## Define Section Variables

Replace these placeholders before using:

- **SECTION_NAME** = [Human-readable name, e.g., "Employee Management" or "Self-Service Portal"]
- **SECTION_ID** = [Folder name in sections/, e.g., "employee-management" or "self-service-portal"]
- **NN** = [Milestone number, e.g., "02" for Employee Management, "06" for Self-Service Portal]

---

I need you to implement the **SECTION_NAME** section of my application.

## Instructions

Please carefully read and analyze the following files:

1. **@product-plan/product-overview.md** — Product summary for overall context
2. **@product-plan/instructions/incremental/NN-SECTION_ID.md** — Specific instructions for this section

Also review the section assets:
- **@product-plan/sections/SECTION_ID/README.md** — Feature overview and design intent
- **@product-plan/sections/SECTION_ID/tests.md** — Test-writing instructions (use TDD approach)
- **@product-plan/sections/SECTION_ID/components/** — React components to integrate
- **@product-plan/sections/SECTION_ID/types.ts** — TypeScript interfaces
- **@product-plan/sections/SECTION_ID/sample-data.json** — Test data

## Before You Begin

Please ask me clarifying questions about:

1. **Authentication & Authorization** (if not yet established)
   - How should users authenticate?
   - What permissions are needed for this section?

2. **Data Relationships**
   - How does this section's data relate to other entities?
   - Are there any cross-section dependencies?

3. **Integration Points**
   - How should this section connect to existing features?
   - Any API endpoints already built that this should use?

4. **Backend Business Logic**
   - Any server-side logic, validations or processes needed beyond what's shown in the UI?
   - Background processes, notifications, or other processes to trigger?

5. **Any Other Clarifications**
   - Questions about specific user flows in this section
   - Edge cases that need clarification

## Implementation Approach

Use test-driven development:
1. Read the `tests.md` file and write failing tests first
2. Implement the feature to make tests pass
3. Refactor while keeping tests green

Lastly, be sure to ask me if I have any other notes to add for this implementation.

Once I answer your questions, proceed with implementation.

---

## Section Reference

| Section | SECTION_NAME | SECTION_ID | NN |
|---------|--------------|------------|-----|
| Foundation | Foundation | — | 01 |
| Employee Management | Employee Management | employee-management | 02 |
| Time & Attendance | Time & Attendance | time-and-attendance | 03 |
| Payroll & Compliance | Payroll & Compliance | payroll-and-compliance | 04 |
| Leave Management | Leave Management | leave-management | 05 |
| Self-Service Portal | Self-Service Portal | self-service-portal | 06 |
