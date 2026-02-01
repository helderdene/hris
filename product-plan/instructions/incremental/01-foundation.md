# Milestone 1: Foundation

> **Provide alongside:** `product-overview.md`
> **Prerequisites:** None

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

Set up the foundational elements: design tokens, data model types, routing structure, and application shell.

## What to Implement

### 1. Design Tokens

Configure your styling system with these tokens:

- See `product-plan/design-system/tokens.css` for CSS custom properties
- See `product-plan/design-system/tailwind-colors.md` for Tailwind configuration
- See `product-plan/design-system/fonts.md` for Google Fonts setup

**Color Palette:**
- Primary: `blue` — Buttons, links, active states
- Secondary: `emerald` — Success indicators, positive badges
- Neutral: `slate` — Backgrounds, text, borders

### 2. Data Model Types

Create TypeScript interfaces for your core entities:

- See `product-plan/data-model/types.ts` for interface definitions
- See `product-plan/data-model/README.md` for entity relationships

**Key Entities:**
- Tenant, Employee, Department, Position
- WorkSchedule, AttendanceLog, DailyTimeRecord
- PayrollPeriod, PayrollRecord, PayrollDeduction
- LeaveType, LeaveBalance, LeaveApplication
- Loan, GovernmentReport, Document, User

### 3. Routing Structure

Create placeholder routes for each section:

| Route | Section |
|-------|---------|
| `/` | Dashboard / Home |
| `/employees/*` | Employee Management |
| `/attendance/*` | Time & Attendance |
| `/payroll/*` | Payroll & Compliance |
| `/leaves/*` | Leave Management |
| `/self-service/*` | Self-Service Portal |
| `/settings` | System Settings |

### 4. Application Shell

Copy the shell components from `product-plan/shell/components/` to your project:

- `AppShell.tsx` — Main layout wrapper with sidebar and header
- `MainNav.tsx` — Navigation component with icons
- `UserMenu.tsx` — User menu with avatar and dropdown

**Wire Up Navigation:**

Connect navigation to your routing:

| Nav Item | Route |
|----------|-------|
| Dashboard | `/` |
| Employees | `/employees` |
| Attendance | `/attendance` |
| Payroll | `/payroll` |
| Leaves | `/leaves` |
| Self-Service | `/self-service` |
| Settings | `/settings` |

**User Menu:**

The user menu expects:
- User name
- Avatar URL (optional, falls back to initials)
- Role badge (HR Admin, Manager, Employee)
- Logout callback

**Shell Features:**
- Collapsible sidebar (256px expanded, 64px collapsed)
- Fixed top header (64px height)
- Mobile-responsive with hamburger menu
- Dark mode support

### 5. Multi-Tenancy Setup

KasamaHR is multi-tenant with subdomain-based routing:

- Each tenant has a unique subdomain (e.g., `acme.kasamahr.com`)
- Tenant data is isolated in the database
- Implement tenant resolution middleware

## Files to Reference

- `product-plan/design-system/` — Design tokens
- `product-plan/data-model/` — Type definitions
- `product-plan/shell/README.md` — Shell design intent
- `product-plan/shell/components/` — Shell React components

## Done When

- [ ] Design tokens are configured (colors, typography)
- [ ] Google Fonts are loaded (DM Sans, JetBrains Mono)
- [ ] Data model types are defined
- [ ] Routes exist for all sections (can be placeholder pages)
- [ ] Shell renders with navigation
- [ ] Navigation links to correct routes
- [ ] User menu shows user info
- [ ] Sidebar can collapse/expand
- [ ] Responsive on mobile (hamburger menu)
- [ ] Dark mode toggle works
