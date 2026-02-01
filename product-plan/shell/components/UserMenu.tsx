import { useState, useRef, useEffect } from 'react'
import { ChevronDown, User, Settings, LogOut } from 'lucide-react'

interface UserMenuProps {
  name: string
  role?: string
  avatarUrl?: string
  onLogout?: () => void
}

export function UserMenu({ name, role, avatarUrl, onLogout }: UserMenuProps) {
  const [isOpen, setIsOpen] = useState(false)
  const menuRef = useRef<HTMLDivElement>(null)

  // Get initials from name
  const initials = name
    .split(' ')
    .map((n) => n[0])
    .join('')
    .toUpperCase()
    .slice(0, 2)

  // Close menu when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsOpen(false)
      }
    }

    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  return (
    <div className="relative" ref={menuRef}>
      {/* Trigger button */}
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
      >
        {/* Avatar */}
        {avatarUrl ? (
          <img
            src={avatarUrl}
            alt={name}
            className="w-8 h-8 rounded-full object-cover"
          />
        ) : (
          <div className="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center">
            <span className="text-white text-sm font-medium">{initials}</span>
          </div>
        )}

        {/* Name and role */}
        <div className="hidden sm:block text-left">
          <p className="text-sm font-medium text-slate-900 dark:text-slate-100">
            {name}
          </p>
          {role && (
            <p className="text-xs text-slate-500 dark:text-slate-400">{role}</p>
          )}
        </div>

        {/* Chevron */}
        <ChevronDown
          className={`w-4 h-4 text-slate-400 transition-transform ${
            isOpen ? 'rotate-180' : ''
          }`}
        />
      </button>

      {/* Dropdown menu */}
      {isOpen && (
        <div className="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-lg shadow-lg border border-slate-200 dark:border-slate-700 py-1 z-50">
          {/* User info header */}
          <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
            <p className="text-sm font-medium text-slate-900 dark:text-slate-100">
              {name}
            </p>
            {role && (
              <span className="inline-block mt-1 px-2 py-0.5 text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded">
                {role}
              </span>
            )}
          </div>

          {/* Menu items */}
          <div className="py-1">
            <button
              type="button"
              onClick={() => setIsOpen(false)}
              className="w-full flex items-center gap-3 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700"
            >
              <User className="w-4 h-4" />
              Profile
            </button>
            <button
              type="button"
              onClick={() => setIsOpen(false)}
              className="w-full flex items-center gap-3 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700"
            >
              <Settings className="w-4 h-4" />
              Preferences
            </button>
          </div>

          {/* Logout */}
          <div className="border-t border-slate-200 dark:border-slate-700 py-1">
            <button
              type="button"
              onClick={() => {
                setIsOpen(false)
                onLogout?.()
              }}
              className="w-full flex items-center gap-3 px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-slate-100 dark:hover:bg-slate-700"
            >
              <LogOut className="w-4 h-4" />
              Logout
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
