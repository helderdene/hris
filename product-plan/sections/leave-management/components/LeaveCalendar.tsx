import { useState, useMemo } from 'react'
import type { LeaveCalendarProps, CalendarEvent, LeaveType } from '../types'

const colorMap: Record<string, { bg: string; text: string; light: string; border: string }> = {
  blue: { bg: 'bg-blue-500', text: 'text-blue-700 dark:text-blue-300', light: 'bg-blue-100 dark:bg-blue-900/50', border: 'border-l-blue-500' },
  emerald: { bg: 'bg-emerald-500', text: 'text-emerald-700 dark:text-emerald-300', light: 'bg-emerald-100 dark:bg-emerald-900/50', border: 'border-l-emerald-500' },
  amber: { bg: 'bg-amber-500', text: 'text-amber-700 dark:text-amber-300', light: 'bg-amber-100 dark:bg-amber-900/50', border: 'border-l-amber-500' },
  pink: { bg: 'bg-pink-500', text: 'text-pink-700 dark:text-pink-300', light: 'bg-pink-100 dark:bg-pink-900/50', border: 'border-l-pink-500' },
  sky: { bg: 'bg-sky-500', text: 'text-sky-700 dark:text-sky-300', light: 'bg-sky-100 dark:bg-sky-900/50', border: 'border-l-sky-500' },
  violet: { bg: 'bg-violet-500', text: 'text-violet-700 dark:text-violet-300', light: 'bg-violet-100 dark:bg-violet-900/50', border: 'border-l-violet-500' },
  rose: { bg: 'bg-rose-500', text: 'text-rose-700 dark:text-rose-300', light: 'bg-rose-100 dark:bg-rose-900/50', border: 'border-l-rose-500' },
  fuchsia: { bg: 'bg-fuchsia-500', text: 'text-fuchsia-700 dark:text-fuchsia-300', light: 'bg-fuchsia-100 dark:bg-fuchsia-900/50', border: 'border-l-fuchsia-500' },
  slate: { bg: 'bg-slate-500', text: 'text-slate-700 dark:text-slate-300', light: 'bg-slate-100 dark:bg-slate-800/50', border: 'border-l-slate-500' },
}

function DayEventsModal({
  date,
  events,
  onEventClick,
  onClose,
}: {
  date: Date
  events: CalendarEvent[]
  onEventClick?: (id: string) => void
  onClose: () => void
}) {
  const dateStr = date.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40" onClick={onClose}>
      <div
        className="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-md w-full max-h-[70vh] overflow-hidden"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="px-5 py-4 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
          <h3 className="font-semibold text-slate-900 dark:text-white">{dateStr}</h3>
          <button onClick={onClose} className="p-1 hover:bg-slate-100 dark:hover:bg-slate-700 rounded-lg transition-colors">
            <svg className="w-5 h-5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div className="p-4 overflow-y-auto max-h-[50vh]">
          {events.length === 0 ? (
            <p className="text-sm text-slate-500 dark:text-slate-400 text-center py-4">No leaves scheduled</p>
          ) : (
            <div className="space-y-3">
              {events.map(event => {
                const colors = colorMap[event.color] || colorMap.slate
                return (
                  <button
                    key={event.id}
                    onClick={() => onEventClick?.(event.id)}
                    className={`w-full text-left p-3 rounded-lg border-l-4 ${colors.border} ${colors.light} hover:opacity-80 transition-opacity`}
                  >
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <p className={`font-medium text-sm ${colors.text}`}>{event.employeeName}</p>
                        <p className="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{event.leaveTypeName}</p>
                      </div>
                      {event.status === 'pending' && (
                        <span className="text-[10px] font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 px-1.5 py-0.5 rounded">
                          Pending
                        </span>
                      )}
                    </div>
                  </button>
                )
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

function EventsSidebar({
  events,
  leaveTypes,
  onEventClick,
}: {
  events: CalendarEvent[]
  leaveTypes: LeaveType[]
  onEventClick?: (id: string) => void
}) {
  const upcomingEvents = events
    .filter(e => new Date(e.endDate) >= new Date())
    .sort((a, b) => new Date(a.startDate).getTime() - new Date(b.startDate).getTime())
    .slice(0, 8)

  const formatDateRange = (start: string, end: string) => {
    const startDate = new Date(start)
    const endDate = new Date(end)
    const startStr = startDate.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
    const endStr = endDate.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
    return start === end ? startStr : `${startStr} - ${endStr}`
  }

  return (
    <div className="space-y-4">
      {/* Upcoming Leaves */}
      <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
        <div className="px-4 py-3 border-b border-slate-200 dark:border-slate-700">
          <h3 className="font-semibold text-slate-900 dark:text-white">Upcoming Leaves</h3>
          <p className="text-xs text-slate-500 dark:text-slate-400">{upcomingEvents.length} scheduled</p>
        </div>
        <div className="p-2 max-h-[300px] overflow-y-auto">
          {upcomingEvents.length === 0 ? (
            <p className="text-sm text-slate-500 dark:text-slate-400 text-center py-6">No upcoming leaves</p>
          ) : (
            <div className="space-y-1">
              {upcomingEvents.map(event => {
                const colors = colorMap[event.color] || colorMap.slate
                return (
                  <button
                    key={event.id}
                    onClick={() => onEventClick?.(event.id)}
                    className="w-full text-left p-3 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                  >
                    <div className="flex items-start gap-3">
                      <div className={`w-1.5 self-stretch rounded-full flex-shrink-0 ${colors.bg}`} />
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-sm text-slate-900 dark:text-white truncate">
                          {event.employeeName}
                        </p>
                        <p className={`text-xs ${colors.text}`}>{event.leaveTypeName}</p>
                        <p className="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                          {formatDateRange(event.startDate, event.endDate)}
                        </p>
                      </div>
                      {event.status === 'pending' && (
                        <span className="w-2 h-2 rounded-full bg-amber-500 flex-shrink-0 mt-1" />
                      )}
                    </div>
                  </button>
                )
              })}
            </div>
          )}
        </div>
      </div>

      {/* Legend */}
      <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
        <h3 className="font-semibold text-slate-900 dark:text-white mb-3">Legend</h3>
        <div className="space-y-2">
          {leaveTypes.slice(0, 6).map(type => {
            const colors = colorMap[type.color] || colorMap.slate
            return (
              <div key={type.id} className="flex items-center gap-2">
                <span className={`w-3 h-3 rounded ${colors.bg}`} />
                <span className="text-xs text-slate-600 dark:text-slate-300">{type.name}</span>
              </div>
            )
          })}
          <div className="flex items-center gap-2 pt-2 border-t border-slate-100 dark:border-slate-700">
            <span className="w-2 h-2 rounded-full bg-amber-500" />
            <span className="text-xs text-slate-500 dark:text-slate-400">Pending approval</span>
          </div>
        </div>
      </div>
    </div>
  )
}

export function LeaveCalendar({
  events,
  employees,
  leaveTypes,
  onEventClick,
  onFilterByEmployee,
  onFilterByDepartment,
  onFilterByType,
  onMonthChange,
}: LeaveCalendarProps) {
  const [currentDate, setCurrentDate] = useState(new Date())
  const [selectedEmployee, setSelectedEmployee] = useState<string | null>(null)
  const [selectedDepartment, setSelectedDepartment] = useState<string | null>(null)
  const [selectedType, setSelectedType] = useState<string | null>(null)
  const [selectedDay, setSelectedDay] = useState<Date | null>(null)

  const departments = useMemo(() => [...new Set(employees.map(e => e.department))], [employees])

  const filteredEvents = useMemo(() => {
    return events.filter(e => {
      if (selectedEmployee && e.employeeId !== selectedEmployee) return false
      if (selectedType && e.leaveTypeId !== selectedType) return false
      if (selectedDepartment) {
        const emp = employees.find(emp => emp.id === e.employeeId)
        if (emp?.department !== selectedDepartment) return false
      }
      return true
    })
  }, [events, selectedEmployee, selectedDepartment, selectedType, employees])

  // Calendar calculations
  const year = currentDate.getFullYear()
  const month = currentDate.getMonth()
  const firstDayOfMonth = new Date(year, month, 1)
  const lastDayOfMonth = new Date(year, month + 1, 0)
  const startPadding = firstDayOfMonth.getDay()
  const daysInMonth = lastDayOfMonth.getDate()

  // Build calendar grid
  const calendarDays: (Date | null)[] = []
  // Previous month padding
  for (let i = 0; i < startPadding; i++) {
    const prevDate = new Date(year, month, -startPadding + i + 1)
    calendarDays.push(prevDate)
  }
  // Current month days
  for (let i = 1; i <= daysInMonth; i++) {
    calendarDays.push(new Date(year, month, i))
  }
  // Next month padding to complete the grid
  const remaining = 42 - calendarDays.length
  for (let i = 1; i <= remaining; i++) {
    calendarDays.push(new Date(year, month + 1, i))
  }

  const getEventsForDate = (date: Date) => {
    const dateStr = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
    return filteredEvents.filter(e => dateStr >= e.startDate && dateStr <= e.endDate)
  }

  const goToPrevMonth = () => {
    const newDate = new Date(year, month - 1, 1)
    setCurrentDate(newDate)
    onMonthChange?.(newDate.getFullYear(), newDate.getMonth())
  }

  const goToNextMonth = () => {
    const newDate = new Date(year, month + 1, 1)
    setCurrentDate(newDate)
    onMonthChange?.(newDate.getFullYear(), newDate.getMonth())
  }

  const goToToday = () => {
    const today = new Date()
    setCurrentDate(today)
    onMonthChange?.(today.getFullYear(), today.getMonth())
  }

  const monthName = currentDate.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
  const today = new Date()

  const isToday = (date: Date) =>
    date.getDate() === today.getDate() &&
    date.getMonth() === today.getMonth() &&
    date.getFullYear() === today.getFullYear()

  const isCurrentMonth = (date: Date) => date.getMonth() === month

  const selectedDayEvents = selectedDay ? getEventsForDate(selectedDay) : []

  return (
    <div className="max-w-7xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Leave Calendar</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            View team absences and plan accordingly
          </p>
        </div>
        <div className="flex items-center gap-3">
          <button
            onClick={goToToday}
            className="px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/50 transition-colors"
          >
            Today
          </button>
          <div className="flex items-center bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-sm">
            <button
              onClick={goToPrevMonth}
              className="p-2.5 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-l-xl transition-colors"
            >
              <svg className="w-5 h-5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <span className="px-6 py-2 text-base font-semibold text-slate-900 dark:text-white min-w-[180px] text-center">
              {monthName}
            </span>
            <button
              onClick={goToNextMonth}
              className="p-2.5 hover:bg-slate-50 dark:hover:bg-slate-700 rounded-r-xl transition-colors"
            >
              <svg className="w-5 h-5 text-slate-600 dark:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-6 p-4 bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700">
        <svg className="w-5 h-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
        </svg>
        <select
          value={selectedDepartment || ''}
          onChange={(e) => {
            const value = e.target.value || null
            setSelectedDepartment(value)
            onFilterByDepartment?.(value)
          }}
          className="px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Departments</option>
          {departments.map(dept => (
            <option key={dept} value={dept}>{dept}</option>
          ))}
        </select>
        <select
          value={selectedEmployee || ''}
          onChange={(e) => {
            const value = e.target.value || null
            setSelectedEmployee(value)
            onFilterByEmployee?.(value)
          }}
          className="px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Employees</option>
          {employees.map(emp => (
            <option key={emp.id} value={emp.id}>{emp.firstName} {emp.lastName}</option>
          ))}
        </select>
        <select
          value={selectedType || ''}
          onChange={(e) => {
            const value = e.target.value || null
            setSelectedType(value)
            onFilterByType?.(value)
          }}
          className="px-3 py-2 text-sm border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Leave Types</option>
          {leaveTypes.map(type => (
            <option key={type.id} value={type.id}>{type.name}</option>
          ))}
        </select>
        {(selectedDepartment || selectedEmployee || selectedType) && (
          <button
            onClick={() => {
              setSelectedDepartment(null)
              setSelectedEmployee(null)
              setSelectedType(null)
              onFilterByDepartment?.(null)
              onFilterByEmployee?.(null)
              onFilterByType?.(null)
            }}
            className="text-sm text-red-600 dark:text-red-400 hover:underline flex items-center gap-1"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
            Clear
          </button>
        )}
      </div>

      <div className="grid xl:grid-cols-4 gap-6">
        {/* Month Calendar Grid */}
        <div className="xl:col-span-3">
          <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
            {/* Weekday Headers */}
            <div className="grid grid-cols-7 bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
              {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day, i) => (
                <div
                  key={day}
                  className={`py-3 text-center text-xs font-semibold uppercase tracking-wider
                    ${i === 0 || i === 6 ? 'text-slate-400 dark:text-slate-500' : 'text-slate-600 dark:text-slate-300'}
                  `}
                >
                  {day}
                </div>
              ))}
            </div>

            {/* Calendar Grid - 6 weeks */}
            <div className="grid grid-cols-7">
              {calendarDays.map((date, i) => {
                if (!date) return <div key={i} className="min-h-[100px] bg-slate-50 dark:bg-slate-900/20" />

                const dayEvents = getEventsForDate(date)
                const dayOfWeek = date.getDay()
                const isWeekend = dayOfWeek === 0 || dayOfWeek === 6
                const isTodayDate = isToday(date)
                const isInCurrentMonth = isCurrentMonth(date)

                return (
                  <div
                    key={i}
                    onClick={() => setSelectedDay(date)}
                    className={`
                      min-h-[100px] p-2 border-b border-r border-slate-100 dark:border-slate-700/50 cursor-pointer transition-colors
                      ${!isInCurrentMonth ? 'bg-slate-50/70 dark:bg-slate-900/30' : isWeekend ? 'bg-slate-50/40 dark:bg-slate-800/30' : 'bg-white dark:bg-slate-800/50'}
                      hover:bg-blue-50/50 dark:hover:bg-blue-900/20
                    `}
                  >
                    {/* Day Number */}
                    <div className="flex items-center justify-between mb-1">
                      <span
                        className={`
                          w-7 h-7 flex items-center justify-center text-sm font-medium rounded-full
                          ${isTodayDate ? 'bg-blue-600 text-white' : ''}
                          ${!isInCurrentMonth ? 'text-slate-300 dark:text-slate-600' : isWeekend ? 'text-slate-400 dark:text-slate-500' : 'text-slate-700 dark:text-slate-200'}
                        `}
                      >
                        {date.getDate()}
                      </span>
                    </div>

                    {/* Events */}
                    <div className="space-y-1">
                      {dayEvents.slice(0, 2).map(event => {
                        const colors = colorMap[event.color] || colorMap.slate
                        return (
                          <div
                            key={event.id}
                            onClick={(e) => {
                              e.stopPropagation()
                              onEventClick?.(event.id)
                            }}
                            className={`
                              relative px-1.5 py-0.5 rounded text-[11px] truncate cursor-pointer
                              ${colors.light} ${colors.text} border-l-2 ${colors.border}
                              hover:opacity-80 transition-opacity
                            `}
                          >
                            {event.employeeName.split(' ')[0]}
                            {event.status === 'pending' && (
                              <span className="absolute right-1 top-1/2 -translate-y-1/2 w-1.5 h-1.5 rounded-full bg-amber-500" />
                            )}
                          </div>
                        )
                      })}
                      {dayEvents.length > 2 && (
                        <div className="text-[10px] text-blue-600 dark:text-blue-400 font-medium px-1">
                          +{dayEvents.length - 2} more
                        </div>
                      )}
                    </div>
                  </div>
                )
              })}
            </div>
          </div>

          {/* Stats Row */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
            <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
              <p className="text-xs text-slate-500 dark:text-slate-400">This Month</p>
              <p className="text-xl font-bold text-slate-900 dark:text-white">
                {filteredEvents.filter(e => {
                  const start = new Date(e.startDate)
                  return start.getMonth() === month && start.getFullYear() === year
                }).length}
              </p>
              <p className="text-xs text-slate-400 dark:text-slate-500">leaves</p>
            </div>
            <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
              <p className="text-xs text-slate-500 dark:text-slate-400">Pending</p>
              <p className="text-xl font-bold text-amber-600 dark:text-amber-400">
                {filteredEvents.filter(e => e.status === 'pending').length}
              </p>
              <p className="text-xs text-slate-400 dark:text-slate-500">for approval</p>
            </div>
            <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
              <p className="text-xs text-slate-500 dark:text-slate-400">On Leave Today</p>
              <p className="text-xl font-bold text-blue-600 dark:text-blue-400">
                {getEventsForDate(today).filter(e => e.status === 'approved').length}
              </p>
              <p className="text-xs text-slate-400 dark:text-slate-500">employees</p>
            </div>
            <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
              <p className="text-xs text-slate-500 dark:text-slate-400">Total Scheduled</p>
              <p className="text-xl font-bold text-emerald-600 dark:text-emerald-400">
                {filteredEvents.length}
              </p>
              <p className="text-xs text-slate-400 dark:text-slate-500">upcoming</p>
            </div>
          </div>
        </div>

        {/* Sidebar */}
        <div className="xl:col-span-1">
          <EventsSidebar
            events={filteredEvents}
            leaveTypes={leaveTypes}
            onEventClick={onEventClick}
          />
        </div>
      </div>

      {/* Day Detail Modal */}
      {selectedDay && (
        <DayEventsModal
          date={selectedDay}
          events={selectedDayEvents}
          onEventClick={onEventClick}
          onClose={() => setSelectedDay(null)}
        />
      )}
    </div>
  )
}
