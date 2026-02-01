import {
  Users,
  Clock,
  AlertCircle,
  Calendar,
  FileEdit,
  Timer,
  Wifi,
  WifiOff,
  ChevronRight,
  LogIn,
  LogOut,
  Coffee,
  Building2,
  Activity,
} from 'lucide-react'
import type { AttendanceDashboardProps, RecentActivity, BiometricDevice } from '../types'

interface StatCardProps {
  title: string
  value: number | string
  subtitle?: string
  icon: React.ReactNode
  variant?: 'default' | 'primary' | 'success' | 'warning' | 'danger'
  onClick?: () => void
}

function StatCard({ title, value, subtitle, icon, variant = 'default', onClick }: StatCardProps) {
  const variantStyles = {
    default: 'bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700',
    primary: 'bg-gradient-to-br from-blue-500 to-blue-600 border-blue-400 text-white',
    success: 'bg-white dark:bg-slate-800 border-emerald-200 dark:border-emerald-800',
    warning: 'bg-white dark:bg-slate-800 border-amber-200 dark:border-amber-800',
    danger: 'bg-white dark:bg-slate-800 border-red-200 dark:border-red-800',
  }

  const iconStyles = {
    default: 'bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-400',
    primary: 'bg-white/20 text-white',
    success: 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400',
    warning: 'bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-400',
    danger: 'bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400',
  }

  const textStyles = {
    default: 'text-slate-900 dark:text-slate-100',
    primary: 'text-white',
    success: 'text-slate-900 dark:text-slate-100',
    warning: 'text-slate-900 dark:text-slate-100',
    danger: 'text-slate-900 dark:text-slate-100',
  }

  const subtitleStyles = {
    default: 'text-slate-500 dark:text-slate-400',
    primary: 'text-blue-100',
    success: 'text-emerald-600 dark:text-emerald-400',
    warning: 'text-amber-600 dark:text-amber-400',
    danger: 'text-red-600 dark:text-red-400',
  }

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={!onClick}
      className={`
        w-full text-left p-5 rounded-xl border transition-all
        ${variantStyles[variant]}
        ${onClick ? 'cursor-pointer hover:shadow-md hover:-translate-y-0.5' : 'cursor-default'}
      `}
    >
      <div className="flex items-start justify-between">
        <div>
          <p className={`text-sm font-medium ${variant === 'primary' ? 'text-blue-100' : 'text-slate-500 dark:text-slate-400'}`}>
            {title}
          </p>
          <p className={`text-3xl font-bold mt-1 ${textStyles[variant]}`}>
            {value}
          </p>
          {subtitle && (
            <p className={`text-sm mt-1 ${subtitleStyles[variant]}`}>
              {subtitle}
            </p>
          )}
        </div>
        <div className={`p-3 rounded-xl ${iconStyles[variant]}`}>
          {icon}
        </div>
      </div>
    </button>
  )
}

function formatTime(dateString: string): string {
  return new Date(dateString).toLocaleTimeString('en-PH', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
}

function formatRelativeTime(dateString: string): string {
  const now = new Date()
  const date = new Date(dateString)
  const diffMs = now.getTime() - date.getTime()
  const diffMins = Math.floor(diffMs / 60000)

  if (diffMins < 1) return 'Just now'
  if (diffMins < 60) return `${diffMins}m ago`
  const diffHours = Math.floor(diffMins / 60)
  if (diffHours < 24) return `${diffHours}h ago`
  return date.toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })
}

function getActionIcon(action: RecentActivity['action']) {
  switch (action) {
    case 'time_in':
      return <LogIn className="w-4 h-4 text-emerald-500" />
    case 'time_out':
      return <LogOut className="w-4 h-4 text-blue-500" />
    case 'break_out':
    case 'break_in':
      return <Coffee className="w-4 h-4 text-amber-500" />
    default:
      return <Clock className="w-4 h-4 text-slate-400" />
  }
}

function getActionLabel(action: RecentActivity['action']): string {
  switch (action) {
    case 'time_in':
      return 'Clocked in'
    case 'time_out':
      return 'Clocked out'
    case 'break_out':
      return 'Break started'
    case 'break_in':
      return 'Break ended'
    default:
      return action
  }
}

interface DeviceStatusCardProps {
  device: BiometricDevice
  onView?: () => void
}

function DeviceStatusCard({ device, onView }: DeviceStatusCardProps) {
  const isOnline = device.status === 'online'

  return (
    <button
      type="button"
      onClick={onView}
      className={`
        w-full text-left p-4 rounded-lg border transition-all
        ${isOnline
          ? 'bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800'
          : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800'
        }
        hover:shadow-sm
      `}
    >
      <div className="flex items-center gap-3">
        <div className={`p-2 rounded-lg ${isOnline ? 'bg-emerald-100 dark:bg-emerald-900/50' : 'bg-red-100 dark:bg-red-900/50'}`}>
          {isOnline ? (
            <Wifi className="w-5 h-5 text-emerald-600 dark:text-emerald-400" />
          ) : (
            <WifiOff className="w-5 h-5 text-red-600 dark:text-red-400" />
          )}
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-medium text-slate-900 dark:text-slate-100 truncate">
            {device.deviceCode}
          </p>
          <p className="text-sm text-slate-500 dark:text-slate-400 truncate">
            {device.workLocation.name}
          </p>
        </div>
        <div className="text-right">
          <span className={`inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium ${
            isOnline
              ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-300'
              : 'bg-red-100 dark:bg-red-900/50 text-red-700 dark:text-red-300'
          }`}>
            <span className={`w-1.5 h-1.5 rounded-full ${isOnline ? 'bg-emerald-500 animate-pulse' : 'bg-red-500'}`} />
            {isOnline ? 'Online' : 'Offline'}
          </span>
        </div>
      </div>
    </button>
  )
}

export function AttendanceDashboard({
  stats,
  devices,
  onViewAllEmployees,
  onViewLateArrivals,
  onViewAbsences,
  onViewPendingCorrections,
  onViewPendingOvertimeRequests,
  onViewDevice,
}: AttendanceDashboardProps) {
  const attendanceRate = Math.round((stats.presentToday / stats.totalEmployees) * 100)
  const onlineDevices = devices.filter((d) => d.status === 'online')
  const offlineDevices = devices.filter((d) => d.status === 'offline')

  return (
    <div className="space-y-6">
      {/* Page Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-slate-100">
            Attendance Dashboard
          </h1>
          <p className="mt-1 text-slate-500 dark:text-slate-400">
            Real-time attendance monitoring for {new Date(stats.date).toLocaleDateString('en-PH', {
              weekday: 'long',
              year: 'numeric',
              month: 'long',
              day: 'numeric',
            })}
          </p>
        </div>
        <div className="flex items-center gap-2">
          <span className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/30 text-emerald-700 dark:text-emerald-400 text-sm font-medium">
            <Activity className="w-4 h-4" />
            Live
          </span>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4">
        <StatCard
          title="Present Today"
          value={stats.presentToday}
          subtitle={`${attendanceRate}% attendance`}
          icon={<Users className="w-6 h-6" />}
          variant="primary"
          onClick={onViewAllEmployees}
        />
        <StatCard
          title="Late Arrivals"
          value={stats.lateToday}
          subtitle="employees"
          icon={<Clock className="w-6 h-6" />}
          variant="warning"
          onClick={onViewLateArrivals}
        />
        <StatCard
          title="Absent"
          value={stats.absentToday}
          subtitle="employees"
          icon={<AlertCircle className="w-6 h-6" />}
          variant="danger"
          onClick={onViewAbsences}
        />
        <StatCard
          title="On Leave"
          value={stats.onLeaveToday}
          subtitle="employees"
          icon={<Calendar className="w-6 h-6" />}
          variant="success"
        />
        <StatCard
          title="Pending Corrections"
          value={stats.pendingCorrections}
          subtitle="requests"
          icon={<FileEdit className="w-6 h-6" />}
          variant="default"
          onClick={onViewPendingCorrections}
        />
        <StatCard
          title="Pending OT"
          value={stats.pendingOvertimeRequests}
          subtitle="requests"
          icon={<Timer className="w-6 h-6" />}
          variant="default"
          onClick={onViewPendingOvertimeRequests}
        />
      </div>

      {/* Main Content Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Recent Activity Feed */}
        <div className="lg:col-span-2 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
          <div className="flex items-center justify-between p-5 border-b border-slate-200 dark:border-slate-700">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              Recent Activity
            </h2>
            <span className="flex items-center gap-1.5 text-sm text-slate-500 dark:text-slate-400">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              Real-time
            </span>
          </div>
          <div className="divide-y divide-slate-100 dark:divide-slate-700">
            {stats.recentActivity.length === 0 ? (
              <div className="p-8 text-center text-slate-500 dark:text-slate-400">
                <Clock className="w-12 h-12 mx-auto mb-3 opacity-50" />
                <p>No activity yet today</p>
              </div>
            ) : (
              stats.recentActivity.map((activity, index) => (
                <div
                  key={`${activity.employeeId}-${activity.time}-${index}`}
                  className="flex items-center gap-4 p-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors"
                >
                  <div className="p-2 rounded-lg bg-slate-100 dark:bg-slate-700">
                    {getActionIcon(activity.action)}
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="font-medium text-slate-900 dark:text-slate-100 truncate">
                      {activity.employeeName}
                    </p>
                    <p className="text-sm text-slate-500 dark:text-slate-400">
                      {getActionLabel(activity.action)} at {activity.device}
                    </p>
                  </div>
                  <div className="text-right">
                    <p className="font-medium text-slate-900 dark:text-slate-100">
                      {formatTime(activity.time)}
                    </p>
                    <p className="text-xs text-slate-500 dark:text-slate-400">
                      {formatRelativeTime(activity.time)}
                    </p>
                  </div>
                </div>
              ))
            )}
          </div>
          {stats.recentActivity.length > 0 && (
            <div className="p-4 border-t border-slate-200 dark:border-slate-700">
              <button
                type="button"
                className="w-full text-center text-sm font-medium text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300"
              >
                View All Activity
              </button>
            </div>
          )}
        </div>

        {/* Device Status */}
        <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
          <div className="flex items-center justify-between p-5 border-b border-slate-200 dark:border-slate-700">
            <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
              Device Status
            </h2>
            <div className="flex items-center gap-3 text-sm">
              <span className="flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                <span className="w-2 h-2 rounded-full bg-emerald-500" />
                {stats.devicesOnline}
              </span>
              <span className="flex items-center gap-1.5 text-red-600 dark:text-red-400">
                <span className="w-2 h-2 rounded-full bg-red-500" />
                {stats.devicesOffline}
              </span>
            </div>
          </div>
          <div className="p-4 space-y-3 max-h-[400px] overflow-y-auto">
            {offlineDevices.length > 0 && (
              <>
                <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                  Offline ({offlineDevices.length})
                </p>
                {offlineDevices.map((device) => (
                  <DeviceStatusCard
                    key={device.id}
                    device={device}
                    onView={() => onViewDevice?.(device.id)}
                  />
                ))}
              </>
            )}
            {onlineDevices.length > 0 && (
              <>
                <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider mt-4">
                  Online ({onlineDevices.length})
                </p>
                {onlineDevices.map((device) => (
                  <DeviceStatusCard
                    key={device.id}
                    device={device}
                    onView={() => onViewDevice?.(device.id)}
                  />
                ))}
              </>
            )}
          </div>
        </div>
      </div>

      {/* Department Attendance */}
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-6">
        <div className="flex items-center justify-between mb-6">
          <h2 className="text-lg font-semibold text-slate-900 dark:text-slate-100">
            Department Attendance
          </h2>
          <button
            type="button"
            className="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 font-medium inline-flex items-center gap-1"
          >
            View Details
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
          {stats.departmentAttendance.map((dept) => {
            const percentage = Math.round((dept.present / dept.total) * 100)
            return (
              <div
                key={dept.department}
                className="p-4 rounded-lg bg-slate-50 dark:bg-slate-700/50 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors cursor-pointer"
              >
                <div className="flex items-center gap-2 mb-3">
                  <Building2 className="w-4 h-4 text-slate-400" />
                  <span className="text-sm font-medium text-slate-600 dark:text-slate-300 truncate">
                    {dept.department}
                  </span>
                </div>
                <div className="flex items-end justify-between mb-2">
                  <span className="text-2xl font-bold text-slate-900 dark:text-slate-100">
                    {dept.present}
                  </span>
                  <span className="text-sm text-slate-500 dark:text-slate-400">
                    / {dept.total}
                  </span>
                </div>
                <div className="h-2 bg-slate-200 dark:bg-slate-600 rounded-full overflow-hidden">
                  <div
                    className="h-full bg-blue-500 rounded-full transition-all duration-500"
                    style={{ width: `${percentage}%` }}
                  />
                </div>
                <p className="text-xs text-slate-500 dark:text-slate-400 mt-1">
                  {percentage}% present
                </p>
              </div>
            )
          })}
        </div>
      </div>
    </div>
  )
}
