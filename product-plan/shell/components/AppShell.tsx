import { useState } from 'react'
import { Menu } from 'lucide-react'
import { MainNav } from './MainNav'
import { UserMenu } from './UserMenu'

export interface NavigationItem {
  label: string
  href: string
  icon: React.ReactNode
  isActive?: boolean
}

export interface AppShellProps {
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

export function AppShell({
  children,
  navigationItems,
  user,
  currentPageTitle,
  onNavigate,
  onLogout,
}: AppShellProps) {
  const [sidebarCollapsed, setSidebarCollapsed] = useState(false)
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-900 font-['DM_Sans']">
      {/* Mobile menu overlay */}
      {mobileMenuOpen && (
        <div
          className="fixed inset-0 bg-black/50 z-40 lg:hidden"
          onClick={() => setMobileMenuOpen(false)}
        />
      )}

      {/* Sidebar */}
      <aside
        className={`
          fixed top-0 left-0 z-50 h-full bg-white dark:bg-slate-900
          border-r border-slate-200 dark:border-slate-700
          transition-all duration-200 ease-in-out
          ${sidebarCollapsed ? 'w-16' : 'w-64'}
          ${mobileMenuOpen ? 'translate-x-0' : '-translate-x-full'}
          lg:translate-x-0
        `}
      >
        <MainNav
          items={navigationItems}
          collapsed={sidebarCollapsed}
          onCollapsedChange={setSidebarCollapsed}
          onNavigate={(href) => {
            onNavigate?.(href)
            setMobileMenuOpen(false)
          }}
        />
      </aside>

      {/* Main content area */}
      <div
        className={`
          transition-all duration-200 ease-in-out
          ${sidebarCollapsed ? 'lg:ml-16' : 'lg:ml-64'}
        `}
      >
        {/* Header */}
        <header className="sticky top-0 z-30 h-16 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 px-4 lg:px-6">
          <div className="flex items-center justify-between h-full">
            {/* Left: Mobile menu button + Page title */}
            <div className="flex items-center gap-4">
              <button
                type="button"
                className="lg:hidden p-2 rounded-md text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700"
                onClick={() => setMobileMenuOpen(true)}
              >
                <Menu className="w-5 h-5" />
              </button>
              {currentPageTitle && (
                <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
                  {currentPageTitle}
                </h1>
              )}
            </div>

            {/* Right: User menu */}
            {user && (
              <UserMenu
                name={user.name}
                role={user.role}
                avatarUrl={user.avatarUrl}
                onLogout={onLogout}
              />
            )}
          </div>
        </header>

        {/* Page content */}
        <main className="p-4 lg:p-6">{children}</main>
      </div>
    </div>
  )
}
