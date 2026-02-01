import { useState } from 'react'
import type { DailyTimeRecord } from '../types'

// =============================================================================
// Component Props
// =============================================================================

export interface DTRViewProps {
  dailyTimeRecords: DailyTimeRecord[]
  onViewDTR?: (id: string) => void
  onRequestDTRCorrection?: (id: string) => void
  onBack?: () => void
}

// =============================================================================
// Utility Functions
// =============================================================================

function formatTime(timeString: string | null): string {
  if (!timeString) return '--:--'
  const date = new Date(timeString)
  return date.toLocaleTimeString('en-PH', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
}

function formatMinutesToHours(minutes: number): string {
  if (minutes === 0) return '-'
  const hours = Math.floor(minutes / 60)
  const mins = minutes % 60
  if (hours > 0 && mins > 0) return `${hours}h ${mins}m`
  if (hours > 0) return `${hours}h`
  return `${mins}m`
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'present':
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
    case 'absent':
      return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    case 'leave':
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'holiday':
      return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
    case 'rest_day':
      return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
  }
}

function getDayTypeLabel(dayType: string): string {
  switch (dayType) {
    case 'regular':
      return 'Regular'
    case 'rest_day':
      return 'Rest Day'
    case 'regular_holiday':
      return 'Regular Holiday'
    case 'special_holiday':
      return 'Special Holiday'
    case 'double_holiday':
      return 'Double Holiday'
    default:
      return dayType
  }
}

function getMonthDays(year: number, month: number): Date[] {
  const days: Date[] = []
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)

  // Add padding for the start of the week
  const startPadding = firstDay.getDay()
  for (let i = startPadding - 1; i >= 0; i--) {
    const date = new Date(year, month, -i)
    days.push(date)
  }

  // Add all days of the month
  for (let i = 1; i <= lastDay.getDate(); i++) {
    days.push(new Date(year, month, i))
  }

  // Add padding for the end of the week
  const endPadding = 6 - lastDay.getDay()
  for (let i = 1; i <= endPadding; i++) {
    days.push(new Date(year, month + 1, i))
  }

  return days
}

// =============================================================================
// Sub-components
// =============================================================================

interface CalendarDayProps {
  date: Date
  isCurrentMonth: boolean
  record?: DailyTimeRecord
  isSelected: boolean
  onSelect: () => void
}

function CalendarDay({ date, isCurrentMonth, record, isSelected, onSelect }: CalendarDayProps) {
  const isToday = new Date().toDateString() === date.toDateString()
  const isWeekend = date.getDay() === 0 || date.getDay() === 6

  const getStatusIndicator = () => {
    if (!record) return null
    switch (record.status) {
      case 'present':
        return <div className="w-2 h-2 rounded-full bg-emerald-500" />
      case 'absent':
        return <div className="w-2 h-2 rounded-full bg-red-500" />
      case 'leave':
        return <div className="w-2 h-2 rounded-full bg-blue-500" />
      case 'holiday':
        return <div className="w-2 h-2 rounded-full bg-purple-500" />
      default:
        return null
    }
  }

  return (
    <button
      onClick={onSelect}
      disabled={!record}
      className={`
        relative aspect-square p-1 sm:p-2 rounded-lg transition-all text-sm
        ${!isCurrentMonth ? 'opacity-30' : ''}
        ${isToday ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-slate-900' : ''}
        ${isSelected ? 'bg-blue-100 dark:bg-blue-900/30' : 'hover:bg-slate-100 dark:hover:bg-slate-800'}
        ${isWeekend && !record ? 'text-slate-400 dark:text-slate-600' : 'text-slate-900 dark:text-white'}
        ${record ? 'cursor-pointer' : 'cursor-default'}
        ${record?.lateMinutes && record.lateMinutes > 0 ? 'border-l-2 border-l-orange-500' : ''}
        ${record?.overtimeMinutes && record.overtimeMinutes > 0 ? 'border-r-2 border-r-emerald-500' : ''}
      `}
    >
      <span className={`font-medium ${isToday ? 'text-blue-600 dark:text-blue-400' : ''}`}>
        {date.getDate()}
      </span>
      <div className="absolute bottom-1 left-1/2 transform -translate-x-1/2">
        {getStatusIndicator()}
      </div>
    </button>
  )
}

interface DTRDetailModalProps {
  record: DailyTimeRecord
  onClose: () => void
  onRequestCorrection?: () => void
}

function DTRDetailModal({ record, onClose, onRequestCorrection }: DTRDetailModalProps) {
  const date = new Date(record.workDate)

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-xl overflow-hidden">
        {/* Header */}
        <div className="p-6 border-b border-slate-100 dark:border-slate-800 bg-gradient-to-r from-purple-600 to-purple-700">
          <div className="flex items-start justify-between">
            <div>
              <p className="text-purple-100 text-sm mb-1">Daily Time Record</p>
              <h2 className="text-xl font-bold text-white">
                {date.toLocaleDateString('en-PH', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })}
              </h2>
            </div>
            <button
              onClick={onClose}
              className="p-1 text-white/70 hover:text-white hover:bg-white/20 rounded-lg transition-colors"
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        {/* Content */}
        <div className="p-6 space-y-6">
          {/* Status & Day Type */}
          <div className="flex items-center gap-3">
            <span className={`px-3 py-1 text-sm font-medium rounded-full ${getStatusColor(record.status)}`}>
              {record.status}
            </span>
            <span className="text-sm text-slate-500 dark:text-slate-400">
              {getDayTypeLabel(record.dayType)}
            </span>
          </div>

          {/* Time In/Out */}
          <div className="grid grid-cols-2 gap-4">
            <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
              <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Time In</p>
              <p className="text-2xl font-bold text-slate-900 dark:text-white font-mono">
                {formatTime(record.actualTimeIn)}
              </p>
              {record.expectedTimeIn && (
                <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
                  Expected: {record.expectedTimeIn}
                </p>
              )}
            </div>
            <div className="p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
              <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Time Out</p>
              <p className="text-2xl font-bold text-slate-900 dark:text-white font-mono">
                {formatTime(record.actualTimeOut)}
              </p>
              {record.expectedTimeOut && (
                <p className="text-xs text-slate-400 dark:text-slate-500 mt-1">
                  Expected: {record.expectedTimeOut}
                </p>
              )}
            </div>
          </div>

          {/* Metrics */}
          <div className="grid grid-cols-4 gap-3">
            <div className="text-center p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
              <p className="text-lg font-bold text-emerald-700 dark:text-emerald-300">
                {record.hoursWorked.toFixed(1)}
              </p>
              <p className="text-xs text-emerald-600 dark:text-emerald-400">Hours</p>
            </div>
            <div className="text-center p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
              <p className="text-lg font-bold text-orange-700 dark:text-orange-300">
                {formatMinutesToHours(record.lateMinutes)}
              </p>
              <p className="text-xs text-orange-600 dark:text-orange-400">Late</p>
            </div>
            <div className="text-center p-3 bg-red-50 dark:bg-red-900/20 rounded-lg">
              <p className="text-lg font-bold text-red-700 dark:text-red-300">
                {formatMinutesToHours(record.undertimeMinutes)}
              </p>
              <p className="text-xs text-red-600 dark:text-red-400">UT</p>
            </div>
            <div className="text-center p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
              <p className="text-lg font-bold text-blue-700 dark:text-blue-300">
                {formatMinutesToHours(record.overtimeMinutes)}
              </p>
              <p className="text-xs text-blue-600 dark:text-blue-400">OT</p>
            </div>
          </div>

          {/* Remarks */}
          {record.remarks && (
            <div className="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
              <p className="text-sm text-amber-800 dark:text-amber-200">
                <span className="font-medium">Remarks:</span> {record.remarks}
              </p>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="p-4 border-t border-slate-100 dark:border-slate-800 flex gap-3">
          <button
            onClick={onClose}
            className="flex-1 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition-colors"
          >
            Close
          </button>
          <button
            onClick={onRequestCorrection}
            className="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors flex items-center justify-center gap-2"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Request Correction
          </button>
        </div>
      </div>
    </div>
  )
}

// =============================================================================
// Main Component
// =============================================================================

export function DTRView({
  dailyTimeRecords,
  onViewDTR,
  onRequestDTRCorrection,
  onBack,
}: DTRViewProps) {
  // Initialize to the month of the most recent record, or current date if no records
  const initialDate = dailyTimeRecords.length > 0
    ? new Date(dailyTimeRecords[0].workDate)
    : new Date()

  const [currentDate, setCurrentDate] = useState(initialDate)
  const [selectedRecordId, setSelectedRecordId] = useState<string | null>(null)
  const [viewMode, setViewMode] = useState<'calendar' | 'list'>('calendar')

  const year = currentDate.getFullYear()
  const month = currentDate.getMonth()
  const days = getMonthDays(year, month)

  const recordsByDate = new Map(
    dailyTimeRecords.map(r => [r.workDate, r])
  )

  const selectedRecord = selectedRecordId
    ? dailyTimeRecords.find(r => r.id === selectedRecordId)
    : null

  const navigateMonth = (direction: number) => {
    setCurrentDate(new Date(year, month + direction, 1))
  }

  // Calculate summary stats for current month
  const currentMonthRecords = dailyTimeRecords.filter(r => {
    const date = new Date(r.workDate)
    return date.getMonth() === month && date.getFullYear() === year
  })

  const totalHours = currentMonthRecords.reduce((sum, r) => sum + r.hoursWorked, 0)
  const totalLate = currentMonthRecords.reduce((sum, r) => sum + r.lateMinutes, 0)
  const totalOT = currentMonthRecords.reduce((sum, r) => sum + r.overtimeMinutes, 0)
  const presentDays = currentMonthRecords.filter(r => r.status === 'present').length

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
      <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex items-center gap-4 mb-4">
            <button
              onClick={onBack}
              className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
              </svg>
            </button>
            <div>
              <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">
                Daily Time Record
              </h1>
              <p className="mt-1 text-slate-500 dark:text-slate-400">
                View your attendance history
              </p>
            </div>
          </div>
        </div>

        {/* Summary Cards */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
          <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Days Present</p>
            <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">{presentDays}</p>
          </div>
          <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Hours Worked</p>
            <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">{totalHours.toFixed(1)}</p>
          </div>
          <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Late (mins)</p>
            <p className="text-2xl font-bold text-orange-600 dark:text-orange-400">{totalLate}</p>
          </div>
          <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4">
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">OT (mins)</p>
            <p className="text-2xl font-bold text-purple-600 dark:text-purple-400">{totalOT}</p>
          </div>
        </div>

        {/* Calendar/List Toggle and Navigation */}
        <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
          <div className="p-4 border-b border-slate-100 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div className="flex items-center gap-2">
              <button
                onClick={() => navigateMonth(-1)}
                className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
              >
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <h2 className="text-lg font-semibold text-slate-900 dark:text-white min-w-[180px] text-center">
                {currentDate.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })}
              </h2>
              <button
                onClick={() => navigateMonth(1)}
                className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
              >
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
            <div className="flex items-center gap-2 bg-slate-100 dark:bg-slate-800 rounded-lg p-1">
              <button
                onClick={() => setViewMode('calendar')}
                className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                  viewMode === 'calendar'
                    ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                }`}
              >
                Calendar
              </button>
              <button
                onClick={() => setViewMode('list')}
                className={`px-3 py-1.5 text-sm font-medium rounded-md transition-colors ${
                  viewMode === 'list'
                    ? 'bg-white dark:bg-slate-700 text-slate-900 dark:text-white shadow-sm'
                    : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'
                }`}
              >
                List
              </button>
            </div>
          </div>

          {viewMode === 'calendar' ? (
            <div className="p-4">
              {/* Weekday Headers */}
              <div className="grid grid-cols-7 gap-1 mb-2">
                {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(day => (
                  <div key={day} className="text-center text-xs font-medium text-slate-500 dark:text-slate-400 py-2">
                    {day}
                  </div>
                ))}
              </div>

              {/* Calendar Grid */}
              <div className="grid grid-cols-7 gap-1">
                {days.map((date, index) => {
                  const dateString = date.toISOString().split('T')[0]
                  const record = recordsByDate.get(dateString)
                  const isCurrentMonth = date.getMonth() === month

                  return (
                    <CalendarDay
                      key={index}
                      date={date}
                      isCurrentMonth={isCurrentMonth}
                      record={record}
                      isSelected={record?.id === selectedRecordId}
                      onSelect={() => {
                        if (record) {
                          setSelectedRecordId(record.id)
                          onViewDTR?.(record.id)
                        }
                      }}
                    />
                  )
                })}
              </div>

              {/* Legend */}
              <div className="mt-4 pt-4 border-t border-slate-100 dark:border-slate-800 flex flex-wrap gap-4 text-xs">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-emerald-500" />
                  <span className="text-slate-600 dark:text-slate-400">Present</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-red-500" />
                  <span className="text-slate-600 dark:text-slate-400">Absent</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-blue-500" />
                  <span className="text-slate-600 dark:text-slate-400">Leave</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-purple-500" />
                  <span className="text-slate-600 dark:text-slate-400">Holiday</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-1 h-4 bg-orange-500 rounded" />
                  <span className="text-slate-600 dark:text-slate-400">Late</span>
                </div>
                <div className="flex items-center gap-2">
                  <div className="w-1 h-4 bg-emerald-500 rounded" />
                  <span className="text-slate-600 dark:text-slate-400">OT</span>
                </div>
              </div>
            </div>
          ) : (
            <div className="divide-y divide-slate-100 dark:divide-slate-800">
              {currentMonthRecords.length === 0 ? (
                <div className="p-8 text-center text-slate-500 dark:text-slate-400">
                  No records for this month
                </div>
              ) : (
                currentMonthRecords.map(record => {
                  const date = new Date(record.workDate)
                  return (
                    <button
                      key={record.id}
                      onClick={() => {
                        setSelectedRecordId(record.id)
                        onViewDTR?.(record.id)
                      }}
                      className="w-full text-left p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors"
                    >
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                          <div className="text-center">
                            <p className="text-2xl font-bold text-slate-900 dark:text-white">
                              {date.getDate()}
                            </p>
                            <p className="text-xs text-slate-500 dark:text-slate-400">
                              {date.toLocaleDateString('en-PH', { weekday: 'short' })}
                            </p>
                          </div>
                          <div>
                            <div className="flex items-center gap-2 mb-1">
                              <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${getStatusColor(record.status)}`}>
                                {record.status}
                              </span>
                              {record.lateMinutes > 0 && (
                                <span className="text-xs text-orange-600 dark:text-orange-400">
                                  Late: {formatMinutesToHours(record.lateMinutes)}
                                </span>
                              )}
                              {record.overtimeMinutes > 0 && (
                                <span className="text-xs text-emerald-600 dark:text-emerald-400">
                                  OT: {formatMinutesToHours(record.overtimeMinutes)}
                                </span>
                              )}
                            </div>
                            <p className="text-sm text-slate-600 dark:text-slate-300 font-mono">
                              {formatTime(record.actualTimeIn)} - {formatTime(record.actualTimeOut)}
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <p className="text-lg font-semibold text-slate-900 dark:text-white">
                            {record.hoursWorked.toFixed(1)}h
                          </p>
                        </div>
                      </div>
                    </button>
                  )
                })
              )}
            </div>
          )}
        </div>
      </div>

      {/* Detail Modal */}
      {selectedRecord && (
        <DTRDetailModal
          record={selectedRecord}
          onClose={() => setSelectedRecordId(null)}
          onRequestCorrection={() => onRequestDTRCorrection?.(selectedRecord.id)}
        />
      )}
    </div>
  )
}
