import type { ReactNode } from 'react'

interface StatCardProps {
  title: string
  value: string | number
  subtitle?: string
  icon?: ReactNode
  trend?: {
    value: number
    label: string
    isPositive?: boolean
  }
  onClick?: () => void
  variant?: 'default' | 'primary' | 'success' | 'warning'
}

export function StatCard({
  title,
  value,
  subtitle,
  icon,
  trend,
  onClick,
  variant = 'default',
}: StatCardProps) {
  const variantStyles = {
    default: 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700',
    primary: 'bg-gradient-to-br from-blue-500 to-blue-600 border-blue-400 text-white',
    success: 'bg-gradient-to-br from-emerald-500 to-emerald-600 border-emerald-400 text-white',
    warning: 'bg-gradient-to-br from-amber-500 to-amber-600 border-amber-400 text-white',
  }

  const textStyles = {
    default: {
      title: 'text-slate-500 dark:text-slate-400',
      value: 'text-slate-900 dark:text-slate-100',
      subtitle: 'text-slate-500 dark:text-slate-400',
    },
    primary: {
      title: 'text-blue-100',
      value: 'text-white',
      subtitle: 'text-blue-100',
    },
    success: {
      title: 'text-emerald-100',
      value: 'text-white',
      subtitle: 'text-emerald-100',
    },
    warning: {
      title: 'text-amber-100',
      value: 'text-white',
      subtitle: 'text-amber-100',
    },
  }

  const isClickable = !!onClick

  return (
    <div
      className={`
        relative overflow-hidden rounded-xl border p-6
        transition-all duration-200
        ${variantStyles[variant]}
        ${isClickable ? 'cursor-pointer hover:shadow-lg hover:-translate-y-0.5' : ''}
      `}
      onClick={onClick}
      role={isClickable ? 'button' : undefined}
      tabIndex={isClickable ? 0 : undefined}
      onKeyDown={isClickable ? (e) => e.key === 'Enter' && onClick?.() : undefined}
    >
      {/* Background decoration */}
      {variant !== 'default' && (
        <div className="absolute top-0 right-0 w-32 h-32 -mr-8 -mt-8 opacity-20">
          <div className="w-full h-full rounded-full bg-white/30" />
        </div>
      )}

      <div className="relative flex items-start justify-between">
        <div className="flex-1">
          <p className={`text-sm font-medium ${textStyles[variant].title}`}>
            {title}
          </p>
          <p className={`mt-2 text-3xl font-bold tracking-tight ${textStyles[variant].value}`}>
            {typeof value === 'number' ? value.toLocaleString() : value}
          </p>
          {subtitle && (
            <p className={`mt-1 text-sm ${textStyles[variant].subtitle}`}>
              {subtitle}
            </p>
          )}
          {trend && (
            <div className="mt-2 flex items-center gap-1">
              <span
                className={`
                  inline-flex items-center text-sm font-medium
                  ${variant === 'default'
                    ? trend.isPositive
                      ? 'text-emerald-600 dark:text-emerald-400'
                      : 'text-red-600 dark:text-red-400'
                    : 'text-white/90'
                  }
                `}
              >
                {trend.isPositive ? '↑' : '↓'} {Math.abs(trend.value)}%
              </span>
              <span className={`text-sm ${textStyles[variant].subtitle}`}>
                {trend.label}
              </span>
            </div>
          )}
        </div>
        {icon && (
          <div
            className={`
              flex-shrink-0 p-3 rounded-lg
              ${variant === 'default'
                ? 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300'
                : 'bg-white/20 text-white'
              }
            `}
          >
            {icon}
          </div>
        )}
      </div>
    </div>
  )
}
