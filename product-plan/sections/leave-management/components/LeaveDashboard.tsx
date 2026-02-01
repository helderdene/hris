import type {
  LeaveDashboardProps,
  LeaveBalance,
  LeaveType,
  LeaveApplication,
  Employee,
  CalendarEvent,
} from '../types'

const colorMap: Record<string, { bg: string; text: string; border: string }> = {
  blue: { bg: 'bg-blue-50 dark:bg-blue-950/30', text: 'text-blue-600 dark:text-blue-400', border: 'border-blue-200 dark:border-blue-800' },
  emerald: { bg: 'bg-emerald-50 dark:bg-emerald-950/30', text: 'text-emerald-600 dark:text-emerald-400', border: 'border-emerald-200 dark:border-emerald-800' },
  amber: { bg: 'bg-amber-50 dark:bg-amber-950/30', text: 'text-amber-600 dark:text-amber-400', border: 'border-amber-200 dark:border-amber-800' },
  pink: { bg: 'bg-pink-50 dark:bg-pink-950/30', text: 'text-pink-600 dark:text-pink-400', border: 'border-pink-200 dark:border-pink-800' },
  sky: { bg: 'bg-sky-50 dark:bg-sky-950/30', text: 'text-sky-600 dark:text-sky-400', border: 'border-sky-200 dark:border-sky-800' },
  violet: { bg: 'bg-violet-50 dark:bg-violet-950/30', text: 'text-violet-600 dark:text-violet-400', border: 'border-violet-200 dark:border-violet-800' },
  rose: { bg: 'bg-rose-50 dark:bg-rose-950/30', text: 'text-rose-600 dark:text-rose-400', border: 'border-rose-200 dark:border-rose-800' },
  fuchsia: { bg: 'bg-fuchsia-50 dark:bg-fuchsia-950/30', text: 'text-fuchsia-600 dark:text-fuchsia-400', border: 'border-fuchsia-200 dark:border-fuchsia-800' },
  slate: { bg: 'bg-slate-50 dark:bg-slate-950/30', text: 'text-slate-600 dark:text-slate-400', border: 'border-slate-200 dark:border-slate-800' },
}

function BalanceCard({ balance, leaveType }: { balance: LeaveBalance; leaveType: LeaveType }) {
  const colors = colorMap[leaveType.color] || colorMap.slate
  const usagePercent = leaveType.maxAccumulation
    ? Math.round((balance.used / leaveType.maxAccumulation) * 100)
    : 0

  return (
    <div className={`rounded-xl border ${colors.border} ${colors.bg} p-4 transition-all hover:shadow-md`}>
      <div className="flex items-start justify-between mb-3">
        <div>
          <p className={`text-xs font-semibold uppercase tracking-wide ${colors.text}`}>{leaveType.code}</p>
          <p className="text-sm font-medium text-slate-700 dark:text-slate-200 mt-0.5">{leaveType.name}</p>
        </div>
        {leaveType.isStatutory && (
          <span className="text-[10px] font-medium bg-blue-100 dark:bg-blue-900/50 text-blue-700 dark:text-blue-300 px-1.5 py-0.5 rounded">
            Statutory
          </span>
        )}
      </div>
      <div className="flex items-end justify-between">
        <div>
          <p className="text-3xl font-bold text-slate-900 dark:text-white">{balance.available}</p>
          <p className="text-xs text-slate-500 dark:text-slate-400">days available</p>
        </div>
        <div className="text-right text-xs text-slate-500 dark:text-slate-400">
          <p>{balance.used} used</p>
          {balance.pending > 0 && <p className="text-amber-600 dark:text-amber-400">{balance.pending} pending</p>}
        </div>
      </div>
      {leaveType.maxAccumulation && (
        <div className="mt-3">
          <div className="h-1.5 bg-slate-200 dark:bg-slate-700 rounded-full overflow-hidden">
            <div
              className={`h-full ${colors.text.replace('text-', 'bg-')} rounded-full transition-all`}
              style={{ width: `${Math.min(usagePercent, 100)}%` }}
            />
          </div>
        </div>
      )}
    </div>
  )
}

function StatCard({ label, value, icon, trend }: { label: string; value: number | string; icon: React.ReactNode; trend?: string }) {
  return (
    <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
      <div className="flex items-center gap-3">
        <div className="w-10 h-10 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
          {icon}
        </div>
        <div>
          <p className="text-2xl font-bold text-slate-900 dark:text-white">{value}</p>
          <p className="text-xs text-slate-500 dark:text-slate-400">{label}</p>
        </div>
      </div>
      {trend && <p className="text-xs text-emerald-600 dark:text-emerald-400 mt-2">{trend}</p>}
    </div>
  )
}

function PendingRequestCard({
  request,
  employee,
  leaveType,
  onView
}: {
  request: LeaveApplication
  employee?: Employee
  leaveType?: LeaveType
  onView?: () => void
}) {
  const colors = colorMap[leaveType?.color || 'slate']
  const formatDate = (date: string) => new Date(date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })

  return (
    <div
      className="flex items-center gap-4 p-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors cursor-pointer group"
      onClick={onView}
    >
      <div className={`w-10 h-10 rounded-full ${colors.bg} ${colors.text} flex items-center justify-center font-semibold text-sm`}>
        {employee ? `${employee.firstName[0]}${employee.lastName[0]}` : '??'}
      </div>
      <div className="flex-1 min-w-0">
        <p className="font-medium text-slate-900 dark:text-white truncate">
          {employee ? `${employee.firstName} ${employee.lastName}` : 'Unknown'}
        </p>
        <p className="text-sm text-slate-500 dark:text-slate-400">
          {leaveType?.name} &middot; {formatDate(request.startDate)} - {formatDate(request.endDate)}
        </p>
      </div>
      <div className="text-right">
        <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${colors.bg} ${colors.text}`}>
          {request.totalDays} {request.totalDays === 1 ? 'day' : 'days'}
        </span>
        <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
          {new Date(request.filedAt).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}
        </p>
      </div>
      <svg className="w-5 h-5 text-slate-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
      </svg>
    </div>
  )
}

function MiniCalendar({ events, onDateClick }: { events: CalendarEvent[]; onDateClick?: (date: string) => void }) {
  const today = new Date()
  const currentMonth = today.getMonth()
  const currentYear = today.getFullYear()

  const firstDay = new Date(currentYear, currentMonth, 1)
  const lastDay = new Date(currentYear, currentMonth + 1, 0)
  const startPadding = firstDay.getDay()
  const daysInMonth = lastDay.getDate()

  const days = []
  for (let i = 0; i < startPadding; i++) {
    days.push(null)
  }
  for (let i = 1; i <= daysInMonth; i++) {
    days.push(i)
  }

  const getEventsForDay = (day: number) => {
    const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
    return events.filter(e => {
      const start = new Date(e.startDate)
      const end = new Date(e.endDate)
      const check = new Date(dateStr)
      return check >= start && check <= end
    })
  }

  const monthName = today.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })

  return (
    <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
      <div className="flex items-center justify-between mb-4">
        <h3 className="font-semibold text-slate-900 dark:text-white">{monthName}</h3>
        <div className="flex items-center gap-1">
          <button className="p-1 hover:bg-slate-100 dark:hover:bg-slate-700 rounded">
            <svg className="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
            </svg>
          </button>
          <button className="p-1 hover:bg-slate-100 dark:hover:bg-slate-700 rounded">
            <svg className="w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
            </svg>
          </button>
        </div>
      </div>
      <div className="grid grid-cols-7 gap-1 text-center text-xs">
        {['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].map(d => (
          <div key={d} className="py-1 text-slate-400 dark:text-slate-500 font-medium">{d}</div>
        ))}
        {days.map((day, i) => {
          if (!day) return <div key={i} />
          const dayEvents = getEventsForDay(day)
          const isToday = day === today.getDate()
          const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`

          return (
            <button
              key={i}
              onClick={() => onDateClick?.(dateStr)}
              className={`
                relative py-1.5 rounded-md text-sm transition-colors
                ${isToday ? 'bg-blue-600 text-white font-semibold' : 'hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300'}
              `}
            >
              {day}
              {dayEvents.length > 0 && !isToday && (
                <span className="absolute bottom-0.5 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full bg-emerald-500" />
              )}
            </button>
          )
        })}
      </div>
      {events.length > 0 && (
        <div className="mt-4 pt-4 border-t border-slate-200 dark:border-slate-700">
          <p className="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">Upcoming leaves</p>
          <div className="space-y-2">
            {events.slice(0, 3).map(event => {
              const colors = colorMap[event.color] || colorMap.slate
              return (
                <div key={event.id} className="flex items-center gap-2 text-xs">
                  <span className={`w-2 h-2 rounded-full ${colors.text.replace('text-', 'bg-')}`} />
                  <span className="text-slate-600 dark:text-slate-300 truncate flex-1">{event.employeeName}</span>
                  <span className="text-slate-400 dark:text-slate-500">
                    {new Date(event.startDate).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}
                  </span>
                </div>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}

export function LeaveDashboard({
  stats,
  balances,
  leaveTypes,
  pendingRequests,
  employees,
  calendarEvents,
  onFileLeave,
  onViewRequest,
  onDateClick,
}: LeaveDashboardProps) {
  const getLeaveType = (id: string) => leaveTypes.find(t => t.id === id)
  const getEmployee = (id: string) => employees.find(e => e.id === id)

  // Get common leave type balances (VL, SL, SIL)
  const commonBalances = balances.filter(b => {
    const type = getLeaveType(b.leaveTypeId)
    return type && ['VL', 'SL', 'SIL'].includes(type.code)
  })

  return (
    <div className="max-w-6xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Leave Management</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Track balances, file requests, and manage approvals</p>
        </div>
        <button
          onClick={onFileLeave}
          className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-lg transition-colors shadow-sm"
        >
          <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
          File Leave
        </button>
      </div>

      {/* Stats Row */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <StatCard
          label="Pending Requests"
          value={stats.pendingRequests}
          icon={<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
        />
        <StatCard
          label="On Leave Today"
          value={stats.onLeaveToday}
          icon={<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" /></svg>}
        />
        <StatCard
          label="Approved This Month"
          value={stats.approvedThisMonth}
          icon={<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>}
        />
        <StatCard
          label="Balance Utilization"
          value={`${stats.averageBalanceUtilization}%`}
          icon={<svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>}
        />
      </div>

      <div className="grid lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Leave Balances */}
          <div>
            <h2 className="text-lg font-semibold text-slate-900 dark:text-white mb-4">My Leave Balances</h2>
            <div className="grid sm:grid-cols-3 gap-4">
              {commonBalances.map(balance => {
                const leaveType = getLeaveType(balance.leaveTypeId)
                if (!leaveType) return null
                return <BalanceCard key={balance.id} balance={balance} leaveType={leaveType} />
              })}
            </div>
          </div>

          {/* Pending Requests */}
          <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
            <div className="flex items-center justify-between p-4 border-b border-slate-200 dark:border-slate-700">
              <h2 className="font-semibold text-slate-900 dark:text-white">Pending for Approval</h2>
              <span className="text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 px-2 py-1 rounded-full">
                {pendingRequests.filter(r => r.status === 'pending').length} requests
              </span>
            </div>
            <div className="divide-y divide-slate-100 dark:divide-slate-700/50">
              {pendingRequests.filter(r => r.status === 'pending').length === 0 ? (
                <div className="p-8 text-center">
                  <div className="w-12 h-12 rounded-full bg-slate-100 dark:bg-slate-700 flex items-center justify-center mx-auto mb-3">
                    <svg className="w-6 h-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                  </div>
                  <p className="text-sm text-slate-500 dark:text-slate-400">No pending requests</p>
                </div>
              ) : (
                pendingRequests
                  .filter(r => r.status === 'pending')
                  .map(request => (
                    <PendingRequestCard
                      key={request.id}
                      request={request}
                      employee={getEmployee(request.employeeId)}
                      leaveType={getLeaveType(request.leaveTypeId)}
                      onView={() => onViewRequest?.(request.id)}
                    />
                  ))
              )}
            </div>
          </div>
        </div>

        {/* Sidebar */}
        <div className="space-y-6">
          <MiniCalendar events={calendarEvents} onDateClick={onDateClick} />

          {/* Quick Actions */}
          <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
            <h3 className="font-semibold text-slate-900 dark:text-white mb-3">Quick Actions</h3>
            <div className="space-y-2">
              <button className="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left">
                <div className="w-8 h-8 rounded-lg bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                  </svg>
                </div>
                <span className="text-sm font-medium text-slate-700 dark:text-slate-200">View My Requests</span>
              </button>
              <button className="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left">
                <div className="w-8 h-8 rounded-lg bg-blue-50 dark:bg-blue-900/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                  </svg>
                </div>
                <span className="text-sm font-medium text-slate-700 dark:text-slate-200">Team Calendar</span>
              </button>
              <button className="w-full flex items-center gap-3 p-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors text-left">
                <div className="w-8 h-8 rounded-lg bg-violet-50 dark:bg-violet-900/30 flex items-center justify-center text-violet-600 dark:text-violet-400">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <span className="text-sm font-medium text-slate-700 dark:text-slate-200">Leave Reports</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}
