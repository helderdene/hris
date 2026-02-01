import { useState } from 'react'
import {
  Search,
  Filter,
  Download,
  Clock,
  AlertTriangle,
  Timer,
  Moon,
  Calendar,
  Palmtree,
  Flag,
  FileEdit,
  ChevronDown,
  Eye,
} from 'lucide-react'
import type { DailyTimeRecordListProps, DailyTimeRecord } from '../types'

const statusConfig: Record<DailyTimeRecord['status'], { label: string; bg: string; text: string; icon: typeof Clock }> = {
  present: {
    label: 'Present',
    bg: 'bg-emerald-100 dark:bg-emerald-900/30',
    text: 'text-emerald-700 dark:text-emerald-400',
    icon: Clock,
  },
  absent: {
    label: 'Absent',
    bg: 'bg-red-100 dark:bg-red-900/30',
    text: 'text-red-700 dark:text-red-400',
    icon: AlertTriangle,
  },
  leave: {
    label: 'On Leave',
    bg: 'bg-blue-100 dark:bg-blue-900/30',
    text: 'text-blue-700 dark:text-blue-400',
    icon: Palmtree,
  },
  holiday: {
    label: 'Holiday',
    bg: 'bg-amber-100 dark:bg-amber-900/30',
    text: 'text-amber-700 dark:text-amber-400',
    icon: Flag,
  },
  rest_day: {
    label: 'Rest Day',
    bg: 'bg-slate-100 dark:bg-slate-700',
    text: 'text-slate-600 dark:text-slate-400',
    icon: Calendar,
  },
}

const dayTypeLabels: Record<DailyTimeRecord['dayType'], string> = {
  regular: 'Regular',
  rest_day: 'Rest Day',
  regular_holiday: 'Regular Holiday',
  special_holiday: 'Special Holiday',
  double_holiday: 'Double Holiday',
}

function formatTime(dateString: string | null): string {
  if (!dateString) return '—'
  return new Date(dateString).toLocaleTimeString('en-PH', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('en-PH', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  })
}

function formatMinutes(minutes: number): string {
  if (minutes === 0) return '—'
  const hrs = Math.floor(minutes / 60)
  const mins = minutes % 60
  if (hrs === 0) return `${mins}m`
  if (mins === 0) return `${hrs}h`
  return `${hrs}h ${mins}m`
}

interface DtrRowProps {
  record: DailyTimeRecord
  onView?: () => void
  onRequestCorrection?: () => void
}

function DtrRow({ record, onView, onRequestCorrection }: DtrRowProps) {
  const status = statusConfig[record.status]
  const StatusIcon = status.icon
  const hasIssues = record.lateMinutes > 0 || record.undertimeMinutes > 0

  return (
    <tr className="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors group">
      {/* Date */}
      <td className="px-4 py-3 whitespace-nowrap">
        <div>
          <p className="font-medium text-slate-900 dark:text-slate-100">
            {formatDate(record.workDate)}
          </p>
          <p className="text-xs text-slate-500 dark:text-slate-400">
            {dayTypeLabels[record.dayType]}
          </p>
        </div>
      </td>

      {/* Employee */}
      <td className="px-4 py-3">
        <div className="flex items-center gap-3">
          <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center flex-shrink-0">
            <span className="text-white font-medium text-sm">
              {record.employee.fullName.split(' ').map((n) => n[0]).slice(0, 2).join('')}
            </span>
          </div>
          <div className="min-w-0">
            <p className="font-medium text-slate-900 dark:text-slate-100 truncate">
              {record.employee.fullName}
            </p>
            <p className="text-sm text-slate-500 dark:text-slate-400 truncate">
              {record.employee.department}
            </p>
          </div>
        </div>
      </td>

      {/* Schedule */}
      <td className="px-4 py-3 whitespace-nowrap hidden lg:table-cell">
        <p className="text-sm text-slate-600 dark:text-slate-300">
          {record.schedule?.name || '—'}
        </p>
      </td>

      {/* Time In */}
      <td className="px-4 py-3 whitespace-nowrap">
        <div>
          <p className="font-mono text-sm text-slate-900 dark:text-slate-100">
            {formatTime(record.actualTimeIn)}
          </p>
          {record.expectedTimeIn && (
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Expected: {record.expectedTimeIn}
            </p>
          )}
        </div>
      </td>

      {/* Time Out */}
      <td className="px-4 py-3 whitespace-nowrap">
        <div>
          <p className="font-mono text-sm text-slate-900 dark:text-slate-100">
            {formatTime(record.actualTimeOut)}
          </p>
          {record.expectedTimeOut && (
            <p className="text-xs text-slate-500 dark:text-slate-400">
              Expected: {record.expectedTimeOut}
            </p>
          )}
        </div>
      </td>

      {/* Hours Worked */}
      <td className="px-4 py-3 whitespace-nowrap hidden md:table-cell">
        <p className="font-medium text-slate-900 dark:text-slate-100">
          {record.hoursWorked ? `${record.hoursWorked.toFixed(1)}h` : '—'}
        </p>
      </td>

      {/* Late/UT/OT/ND */}
      <td className="px-4 py-3 whitespace-nowrap hidden xl:table-cell">
        <div className="flex flex-wrap gap-1.5">
          {record.lateMinutes > 0 && (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400">
              <AlertTriangle className="w-3 h-3" />
              {formatMinutes(record.lateMinutes)}
            </span>
          )}
          {record.undertimeMinutes > 0 && (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400">
              <Clock className="w-3 h-3" />
              {formatMinutes(record.undertimeMinutes)}
            </span>
          )}
          {record.overtimeMinutes > 0 && (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400">
              <Timer className="w-3 h-3" />
              {formatMinutes(record.overtimeMinutes)}
            </span>
          )}
          {record.nightDiffMinutes > 0 && (
            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-400">
              <Moon className="w-3 h-3" />
              {formatMinutes(record.nightDiffMinutes)}
            </span>
          )}
          {!record.lateMinutes && !record.undertimeMinutes && !record.overtimeMinutes && !record.nightDiffMinutes && (
            <span className="text-slate-400 dark:text-slate-500">—</span>
          )}
        </div>
      </td>

      {/* Status */}
      <td className="px-4 py-3 whitespace-nowrap">
        <span className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${status.bg} ${status.text}`}>
          <StatusIcon className="w-3.5 h-3.5" />
          {status.label}
        </span>
      </td>

      {/* Actions */}
      <td className="px-4 py-3 whitespace-nowrap">
        <div className="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
          <button
            type="button"
            onClick={onView}
            className="p-2 text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
            title="View Details"
          >
            <Eye className="w-4 h-4" />
          </button>
          {hasIssues && (
            <button
              type="button"
              onClick={onRequestCorrection}
              className="p-2 text-slate-400 hover:text-amber-600 dark:hover:text-amber-400 hover:bg-amber-50 dark:hover:bg-amber-900/30 rounded-lg transition-colors"
              title="Request Correction"
            >
              <FileEdit className="w-4 h-4" />
            </button>
          )}
        </div>
      </td>
    </tr>
  )
}

export function DailyTimeRecordList({
  records,
  onView,
  onRequestCorrection,
  onExport,
}: DailyTimeRecordListProps) {
  const [searchQuery, setSearchQuery] = useState('')

  const filteredRecords = records.filter((record) =>
    record.employee.fullName.toLowerCase().includes(searchQuery.toLowerCase()) ||
    record.employee.employeeNumber.toLowerCase().includes(searchQuery.toLowerCase()) ||
    record.employee.department.toLowerCase().includes(searchQuery.toLowerCase())
  )

  // Summary stats
  const presentCount = records.filter((r) => r.status === 'present').length
  const lateCount = records.filter((r) => r.lateMinutes > 0).length
  const withOTCount = records.filter((r) => r.overtimeMinutes > 0).length

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">
            Daily Time Records
          </h1>
          <p className="mt-1 text-slate-500 dark:text-slate-400">
            {records.length} records
          </p>
        </div>
        <div className="flex gap-3">
          <button
            type="button"
            onClick={onExport}
            className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
          >
            <Download className="w-4 h-4" />
            Export DTR
          </button>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-emerald-100 dark:bg-emerald-900/50">
              <Clock className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
            </div>
            <div>
              <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">{presentCount}</p>
              <p className="text-sm text-slate-500 dark:text-slate-400">Present</p>
            </div>
          </div>
        </div>
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-red-100 dark:bg-red-900/50">
              <AlertTriangle className="w-5 h-5 text-red-600 dark:text-red-400" />
            </div>
            <div>
              <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">{lateCount}</p>
              <p className="text-sm text-slate-500 dark:text-slate-400">With Late</p>
            </div>
          </div>
        </div>
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <div className="flex items-center gap-3">
            <div className="p-2 rounded-lg bg-blue-100 dark:bg-blue-900/50">
              <Timer className="w-5 h-5 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <p className="text-2xl font-bold text-slate-900 dark:text-slate-100">{withOTCount}</p>
              <p className="text-sm text-slate-500 dark:text-slate-400">With OT</p>
            </div>
          </div>
        </div>
      </div>

      {/* Search & Filters */}
      <div className="flex flex-col sm:flex-row gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-slate-400" />
          <input
            type="text"
            placeholder="Search by name, employee number, or department..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg text-slate-900 dark:text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <button
          type="button"
          className="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-slate-700 dark:text-slate-200 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
        >
          <Filter className="w-4 h-4" />
          Filters
          <ChevronDown className="w-4 h-4" />
        </button>
      </div>

      {/* DTR Table */}
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full">
            <thead>
              <tr className="bg-slate-50 dark:bg-slate-700/50 border-b border-slate-200 dark:border-slate-700">
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Date
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Employee
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden lg:table-cell">
                  Schedule
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Time In
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Time Out
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden md:table-cell">
                  Hours
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider hidden xl:table-cell">
                  Late/UT/OT/ND
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Status
                </th>
                <th className="px-4 py-3 text-left text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Actions
                </th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 dark:divide-slate-700">
              {filteredRecords.length === 0 ? (
                <tr>
                  <td colSpan={9} className="px-4 py-12 text-center">
                    <Calendar className="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                    <p className="text-slate-500 dark:text-slate-400">No records found</p>
                  </td>
                </tr>
              ) : (
                filteredRecords.map((record) => (
                  <DtrRow
                    key={record.id}
                    record={record}
                    onView={() => onView?.(record.id)}
                    onRequestCorrection={() => onRequestCorrection?.(record.id)}
                  />
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  )
}
