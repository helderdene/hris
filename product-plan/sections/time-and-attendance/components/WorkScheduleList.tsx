import { useState } from 'react'
import {
  CalendarDays,
  Clock,
  Users,
  Plus,
  Search,
  Eye,
  Pencil,
  Trash2,
  UserPlus,
  Check,
  X,
} from 'lucide-react'
import type { WorkScheduleListProps, WorkSchedule, ScheduleDetail } from '../../../../product/sections/time-and-attendance/types'

// Schedule type display configuration
const scheduleTypeConfig: Record<WorkSchedule['scheduleType'], { label: string; color: string }> = {
  fixed: { label: 'Fixed', color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300' },
  flexible: { label: 'Flexible', color: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300' },
  shifting: { label: 'Shifting', color: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300' },
  compressed: { label: 'Compressed', color: 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300' },
}

// Format time string for display
function formatTime(time: string | null): string {
  if (!time) return '-'
  const [hours, minutes] = time.split(':')
  const hour = parseInt(hours, 10)
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const displayHour = hour % 12 || 12
  return `${displayHour}:${minutes} ${ampm}`
}

// Schedule Card Component
function ScheduleCard({
  schedule,
  onView,
  onEdit,
  onDelete,
  onAssign,
}: {
  schedule: WorkSchedule
  onView?: (id: string) => void
  onEdit?: (id: string) => void
  onDelete?: (id: string) => void
  onAssign?: (id: string) => void
}) {
  const typeConfig = scheduleTypeConfig[schedule.scheduleType]

  // Get work days summary
  const workDays = schedule.details.filter(d => !d.isRestDay)
  const restDays = schedule.details.filter(d => d.isRestDay)

  // Get typical time (from first work day)
  const typicalDay = workDays[0]

  return (
    <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6 hover:shadow-md transition-shadow">
      {/* Header */}
      <div className="flex items-start justify-between mb-4">
        <div className="flex-1">
          <div className="flex items-center gap-3 mb-2">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-white">
              {schedule.name}
            </h3>
            <span className={`px-2.5 py-0.5 rounded-full text-xs font-medium ${typeConfig.color}`}>
              {typeConfig.label}
            </span>
          </div>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            Code: {schedule.code}
          </p>
        </div>
        <div className="flex items-center gap-1">
          {schedule.isActive ? (
            <span className="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
              <Check className="w-3 h-3" />
              Active
            </span>
          ) : (
            <span className="flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400">
              <X className="w-3 h-3" />
              Inactive
            </span>
          )}
        </div>
      </div>

      {/* Description */}
      {schedule.description && (
        <p className="text-sm text-slate-600 dark:text-slate-300 mb-4">
          {schedule.description}
        </p>
      )}

      {/* Schedule Details */}
      <div className="grid grid-cols-2 gap-4 mb-4">
        <div className="flex items-center gap-2 text-sm">
          <Clock className="w-4 h-4 text-slate-400" />
          <span className="text-slate-600 dark:text-slate-300">
            {schedule.workHoursPerDay}h/day, {schedule.workDaysPerWeek} days/week
          </span>
        </div>
        <div className="flex items-center gap-2 text-sm">
          <CalendarDays className="w-4 h-4 text-slate-400" />
          <span className="text-slate-600 dark:text-slate-300">
            {schedule.gracePeriodMinutes} min grace period
          </span>
        </div>
      </div>

      {/* Typical Schedule */}
      {typicalDay && (
        <div className="bg-slate-50 dark:bg-slate-900/50 rounded-lg p-3 mb-4">
          <div className="text-xs font-medium text-slate-500 dark:text-slate-400 mb-2">
            Typical Schedule
          </div>
          <div className="flex items-center justify-between text-sm">
            <div>
              <span className="text-slate-500 dark:text-slate-400">In: </span>
              <span className="font-medium text-slate-900 dark:text-white">
                {formatTime(typicalDay.timeIn)}
              </span>
            </div>
            <div>
              <span className="text-slate-500 dark:text-slate-400">Out: </span>
              <span className="font-medium text-slate-900 dark:text-white">
                {formatTime(typicalDay.timeOut)}
              </span>
            </div>
            {typicalDay.breakStart && typicalDay.breakEnd && (
              <div>
                <span className="text-slate-500 dark:text-slate-400">Break: </span>
                <span className="font-medium text-slate-900 dark:text-white">
                  {typicalDay.breakMinutes} min
                </span>
              </div>
            )}
          </div>
        </div>
      )}

      {/* Work Days Grid */}
      <div className="flex gap-1 mb-4">
        {schedule.details.map((day) => (
          <div
            key={day.dayOfWeek}
            className={`flex-1 text-center py-1.5 rounded text-xs font-medium ${
              day.isRestDay
                ? 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500'
                : 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300'
            }`}
            title={day.isRestDay ? 'Rest Day' : `${formatTime(day.timeIn)} - ${formatTime(day.timeOut)}`}
          >
            {day.dayName.slice(0, 3)}
          </div>
        ))}
      </div>

      {/* Footer */}
      <div className="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-slate-700">
        <div className="flex items-center gap-2 text-sm text-slate-500 dark:text-slate-400">
          <Users className="w-4 h-4" />
          <span>{schedule.employeeCount} employees</span>
        </div>

        <div className="flex items-center gap-1">
          {onAssign && (
            <button
              onClick={() => onAssign(schedule.id)}
              className="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
              title="Assign employees"
            >
              <UserPlus className="w-4 h-4" />
            </button>
          )}
          {onView && (
            <button
              onClick={() => onView(schedule.id)}
              className="p-2 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
              title="View details"
            >
              <Eye className="w-4 h-4" />
            </button>
          )}
          {onEdit && (
            <button
              onClick={() => onEdit(schedule.id)}
              className="p-2 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-colors"
              title="Edit schedule"
            >
              <Pencil className="w-4 h-4" />
            </button>
          )}
          {onDelete && (
            <button
              onClick={() => onDelete(schedule.id)}
              className="p-2 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
              title="Delete schedule"
            >
              <Trash2 className="w-4 h-4" />
            </button>
          )}
        </div>
      </div>
    </div>
  )
}

export function WorkScheduleList({
  schedules,
  onView,
  onEdit,
  onDelete,
  onCreate,
  onAssign,
}: WorkScheduleListProps) {
  const [searchQuery, setSearchQuery] = useState('')
  const [typeFilter, setTypeFilter] = useState<WorkSchedule['scheduleType'] | 'all'>('all')
  const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>('all')

  // Filter schedules
  const filteredSchedules = schedules.filter((schedule) => {
    const matchesSearch =
      schedule.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      schedule.code.toLowerCase().includes(searchQuery.toLowerCase()) ||
      schedule.description.toLowerCase().includes(searchQuery.toLowerCase())

    const matchesType = typeFilter === 'all' || schedule.scheduleType === typeFilter
    const matchesStatus =
      statusFilter === 'all' ||
      (statusFilter === 'active' && schedule.isActive) ||
      (statusFilter === 'inactive' && !schedule.isActive)

    return matchesSearch && matchesType && matchesStatus
  })

  // Count by type
  const typeCounts = schedules.reduce(
    (acc, s) => {
      acc[s.scheduleType] = (acc[s.scheduleType] || 0) + 1
      return acc
    },
    {} as Record<string, number>
  )

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">
            Work Schedules
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Manage employee work schedules and shift configurations
          </p>
        </div>
        {onCreate && (
          <button
            onClick={onCreate}
            className="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors"
          >
            <Plus className="w-4 h-4" />
            Create Schedule
          </button>
        )}
      </div>

      {/* Type Summary Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {Object.entries(scheduleTypeConfig).map(([type, config]) => (
          <button
            key={type}
            onClick={() => setTypeFilter(typeFilter === type ? 'all' : type as WorkSchedule['scheduleType'])}
            className={`p-4 rounded-xl border transition-all ${
              typeFilter === type
                ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-slate-300 dark:hover:border-slate-600'
            }`}
          >
            <div className="flex items-center justify-between mb-2">
              <span className={`px-2 py-0.5 rounded-full text-xs font-medium ${config.color}`}>
                {config.label}
              </span>
              <span className="text-2xl font-bold text-slate-900 dark:text-white">
                {typeCounts[type] || 0}
              </span>
            </div>
            <p className="text-xs text-slate-500 dark:text-slate-400 text-left">
              {type === 'fixed' && 'Standard fixed hours'}
              {type === 'flexible' && 'Flexible time in/out'}
              {type === 'shifting' && 'Rotating shifts'}
              {type === 'compressed' && '4-day work week'}
            </p>
          </button>
        ))}
      </div>

      {/* Filters */}
      <div className="flex flex-col sm:flex-row gap-4">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
          <input
            type="text"
            placeholder="Search schedules..."
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            className="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
          />
        </div>
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value as 'all' | 'active' | 'inactive')}
          className="px-4 py-2.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        >
          <option value="all">All Status</option>
          <option value="active">Active Only</option>
          <option value="inactive">Inactive Only</option>
        </select>
        {typeFilter !== 'all' && (
          <button
            onClick={() => setTypeFilter('all')}
            className="px-4 py-2.5 text-sm text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-700 rounded-lg flex items-center gap-2"
          >
            <X className="w-4 h-4" />
            Clear Filter
          </button>
        )}
      </div>

      {/* Results Count */}
      <div className="text-sm text-slate-500 dark:text-slate-400">
        Showing {filteredSchedules.length} of {schedules.length} schedules
      </div>

      {/* Schedule Grid */}
      {filteredSchedules.length > 0 ? (
        <div className="grid md:grid-cols-2 gap-6">
          {filteredSchedules.map((schedule) => (
            <ScheduleCard
              key={schedule.id}
              schedule={schedule}
              onView={onView}
              onEdit={onEdit}
              onDelete={onDelete}
              onAssign={onAssign}
            />
          ))}
        </div>
      ) : (
        <div className="text-center py-12 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700">
          <CalendarDays className="w-12 h-12 text-slate-300 dark:text-slate-600 mx-auto mb-4" />
          <h3 className="text-lg font-medium text-slate-900 dark:text-white mb-2">
            No schedules found
          </h3>
          <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
            {searchQuery || typeFilter !== 'all' || statusFilter !== 'all'
              ? 'Try adjusting your search or filters'
              : 'Get started by creating your first work schedule'}
          </p>
          {onCreate && !searchQuery && typeFilter === 'all' && statusFilter === 'all' && (
            <button
              onClick={onCreate}
              className="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium transition-colors"
            >
              <Plus className="w-4 h-4" />
              Create Schedule
            </button>
          )}
        </div>
      )}
    </div>
  )
}
