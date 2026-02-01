# Application Shell

## Overview

KasamaHR uses a collapsible sidebar navigation pattern optimized for enterprise HR workflows. The shell provides persistent navigation across all modules while maximizing content area when needed.

## Design Intent

- **Enterprise-focused**: Supports many navigation items without overwhelming users
- **Collapsible**: Users can collapse sidebar to icon-only mode for more content space
- **Mobile-ready**: Sidebar becomes slide-out drawer on mobile devices
- **Consistent branding**: Logo and product name always visible

## Navigation Structure

| Nav Item | Route | Icon |
|----------|-------|------|
| Dashboard | `/` | Home/Grid |
| Employees | `/employees` | Users |
| Attendance | `/attendance` | Clock |
| Payroll | `/payroll` | DollarSign |
| Leaves | `/leaves` | Calendar |
| Self-Service | `/self-service` | User |
| *divider* | — | — |
| Settings | `/settings` | Settings |

## User Menu

Located in the top-right header:

- **Avatar**: User photo or initials fallback
- **Name**: Full name displayed
- **Role badge**: "HR Admin", "Manager", "Employee"
- **Dropdown items**: Profile, Preferences, Logout

## Layout Specifications

### Sidebar

| State | Width | Content |
|-------|-------|---------|
| Expanded | 256px (16rem) | Icons + labels |
| Collapsed | 64px (4rem) | Icons only with tooltips |

### Header

- Height: 64px (4rem)
- Position: Sticky top
- Contains: Mobile menu button (left), Page title (left), User menu (right)

### Content Area

- Padding: 24px on desktop, 16px on mobile
- Scrollable
- Full width within the remaining space

## Responsive Behavior

| Breakpoint | Sidebar State |
|------------|---------------|
| Desktop (1024px+) | Expanded by default, user can collapse |
| Tablet (768-1023px) | Collapsed by default, can expand |
| Mobile (<768px) | Hidden, hamburger menu triggers slide-out |

## Components

### AppShell.tsx

Main layout wrapper that composes sidebar and header.

**Props:**
```typescript
interface AppShellProps {
  children: React.ReactNode
  navigationItems: NavigationItem[]
  user?: {
    name: string
    role?: string
    avatarUrl?: string
  }
  currentPageTitle?: string
  onNavigate?: (href: string) => void
  onLogout?: () => void
}
```

### MainNav.tsx

Sidebar navigation component.

**Features:**
- Renders navigation items from props
- Handles collapsed/expanded state
- Highlights active item
- Bottom section for Settings + Collapse toggle

### UserMenu.tsx

User dropdown in header.

**Features:**
- Avatar with initials fallback
- Role badge
- Dropdown with Profile, Preferences, Logout

## Usage

```tsx
import { AppShell } from './components'
import { Home, Users, Clock, DollarSign, Calendar, User, Settings } from 'lucide-react'

function App() {
  const navigationItems = [
    { label: 'Dashboard', href: '/', icon: <Home />, isActive: true },
    { label: 'Employees', href: '/employees', icon: <Users /> },
    { label: 'Attendance', href: '/attendance', icon: <Clock /> },
    { label: 'Payroll', href: '/payroll', icon: <DollarSign /> },
    { label: 'Leaves', href: '/leaves', icon: <Calendar /> },
    { label: 'Self-Service', href: '/self-service', icon: <User /> },
    { label: 'Settings', href: '/settings', icon: <Settings /> },
  ]

  return (
    <AppShell
      navigationItems={navigationItems}
      user={{ name: 'Maria Santos', role: 'HR Admin' }}
      currentPageTitle="Dashboard"
      onNavigate={(href) => router.push(href)}
      onLogout={() => logout()}
    >
      <YourPageContent />
    </AppShell>
  )
}
```

## Styling Notes

- Uses Tailwind CSS classes
- Supports dark mode via `dark:` variants
- Colors from design system: `blue` (primary), `slate` (neutral)
- Typography: DM Sans
- Icons: Lucide React
- Transitions: 200ms ease for collapse/expand
