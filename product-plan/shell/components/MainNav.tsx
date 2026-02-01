import { ChevronLeft, ChevronRight } from 'lucide-react'
import type { NavigationItem } from './AppShell'

interface MainNavProps {
  items: NavigationItem[]
  collapsed: boolean
  onCollapsedChange: (collapsed: boolean) => void
  onNavigate?: (href: string) => void
}

export function MainNav({
  items,
  collapsed,
  onCollapsedChange,
  onNavigate,
}: MainNavProps) {
  // Split items into main nav and settings (last item)
  const mainItems = items.slice(0, -1)
  const settingsItem = items[items.length - 1]

  return (
    <div className="flex flex-col h-full">
      {/* Logo area */}
      <div className="h-16 flex items-center px-4 border-b border-slate-200 dark:border-slate-700">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-lg bg-blue-500 flex items-center justify-center">
            <span className="text-white font-bold text-sm">K</span>
          </div>
          {!collapsed && (
            <span className="text-slate-900 dark:text-white font-semibold text-lg">KasamaHR</span>
          )}
        </div>
      </div>

      {/* Main navigation */}
      <nav className="flex-1 py-4 overflow-y-auto">
        <ul className="space-y-1 px-2">
          {mainItems.map((item) => (
            <li key={item.href}>
              <button
                type="button"
                onClick={() => onNavigate?.(item.href)}
                className={`
                  w-full flex items-center gap-3 px-3 py-2.5 rounded-lg
                  transition-colors duration-150
                  ${
                    item.isActive
                      ? 'bg-blue-600 text-white'
                      : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
                  }
                  ${collapsed ? 'justify-center' : ''}
                `}
                title={collapsed ? item.label : undefined}
              >
                <span className="w-5 h-5 flex-shrink-0">{item.icon}</span>
                {!collapsed && (
                  <span className="font-medium text-sm">{item.label}</span>
                )}
              </button>
            </li>
          ))}
        </ul>
      </nav>

      {/* Settings + Collapse toggle */}
      <div className="border-t border-slate-200 dark:border-slate-700 py-4 px-2">
        {/* Settings item */}
        {settingsItem && (
          <button
            type="button"
            onClick={() => onNavigate?.(settingsItem.href)}
            className={`
              w-full flex items-center gap-3 px-3 py-2.5 rounded-lg
              transition-colors duration-150 mb-2
              ${
                settingsItem.isActive
                  ? 'bg-blue-600 text-white'
                  : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-900 dark:hover:text-white'
              }
              ${collapsed ? 'justify-center' : ''}
            `}
            title={collapsed ? settingsItem.label : undefined}
          >
            <span className="w-5 h-5 flex-shrink-0">{settingsItem.icon}</span>
            {!collapsed && (
              <span className="font-medium text-sm">{settingsItem.label}</span>
            )}
          </button>
        )}

        {/* Collapse toggle */}
        <button
          type="button"
          onClick={() => onCollapsedChange(!collapsed)}
          className={`
            w-full flex items-center gap-3 px-3 py-2.5 rounded-lg
            text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 hover:text-slate-700 dark:hover:text-slate-300
            transition-colors duration-150
            ${collapsed ? 'justify-center' : ''}
          `}
          title={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {collapsed ? (
            <ChevronRight className="w-5 h-5" />
          ) : (
            <>
              <ChevronLeft className="w-5 h-5" />
              <span className="font-medium text-sm">Collapse</span>
            </>
          )}
        </button>
      </div>
    </div>
  )
}
