import { useState } from 'react'
import type { LeaveBalance, LeaveApplication } from '../types'

// =============================================================================
// Component Props
// =============================================================================

export interface LeaveManagementProps {
  leaveBalances: LeaveBalance[]
  leaveApplications: LeaveApplication[]
  onFileLeave?: () => void
  onViewLeaveApplication?: (id: string) => void
  onCancelLeaveApplication?: (id: string) => void
  onBack?: () => void
}

// =============================================================================
// Utility Functions
// =============================================================================

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function formatDateRange(start: string, end: string): string {
  if (start === end) return formatDate(start)
  return `${formatDate(start)} - ${formatDate(end)}`
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'approved':
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
    case 'pending':
      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
    case 'rejected':
      return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    case 'cancelled':
      return 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400'
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
  }
}

function getLeaveTypeColor(code: string): string {
  switch (code) {
    case 'VL':
      return 'bg-blue-500'
    case 'SL':
      return 'bg-red-500'
    case 'SIL':
      return 'bg-emerald-500'
    case 'EL':
      return 'bg-orange-500'
    default:
      return 'bg-slate-500'
  }
}

// =============================================================================
// Sub-components
// =============================================================================

interface LeaveBalanceCardProps {
  balance: LeaveBalance
}

function LeaveBalanceCard({ balance }: LeaveBalanceCardProps) {
  const percentage = balance.earned > 0 ? (balance.available / balance.earned) * 100 : 0

  return (
    <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-5">
      <div className="flex items-start justify-between mb-4">
        <div className="flex items-center gap-3">
          <div className={`w-10 h-10 rounded-lg ${getLeaveTypeColor(balance.leaveTypeCode)} flex items-center justify-center`}>
            <span className="text-xs font-bold text-white">{balance.leaveTypeCode}</span>
          </div>
          <div>
            <h3 className="font-semibold text-slate-900 dark:text-white">{balance.leaveTypeName}</h3>
            <p className="text-xs text-slate-500 dark:text-slate-400">Year {balance.year}</p>
          </div>
        </div>
      </div>

      <div className="mb-4">
        <div className="flex items-baseline justify-between mb-2">
          <span className="text-3xl font-bold text-slate-900 dark:text-white">{balance.available}</span>
          <span className="text-sm text-slate-500 dark:text-slate-400">of {balance.earned} days</span>
        </div>
        <div className="h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
          <div
            className={`h-full ${getLeaveTypeColor(balance.leaveTypeCode)} rounded-full transition-all duration-500`}
            style={{ width: `${percentage}%` }}
          />
        </div>
      </div>

      <div className="grid grid-cols-3 gap-2 text-center">
        <div className="p-2 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
          <p className="text-lg font-semibold text-slate-900 dark:text-white">{balance.used}</p>
          <p className="text-xs text-slate-500 dark:text-slate-400">Used</p>
        </div>
        <div className="p-2 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
          <p className="text-lg font-semibold text-amber-600 dark:text-amber-400">{balance.pending}</p>
          <p className="text-xs text-slate-500 dark:text-slate-400">Pending</p>
        </div>
        <div className="p-2 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
          <p className="text-lg font-semibold text-slate-900 dark:text-white">{balance.broughtForward}</p>
          <p className="text-xs text-slate-500 dark:text-slate-400">Carried</p>
        </div>
      </div>
    </div>
  )
}

interface LeaveApplicationRowProps {
  application: LeaveApplication
  onView?: () => void
  onCancel?: () => void
}

function LeaveApplicationRow({ application, onView, onCancel }: LeaveApplicationRowProps) {
  return (
    <div
      onClick={onView}
      className="p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors cursor-pointer"
    >
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div className="flex items-start gap-4">
          <div className={`w-10 h-10 rounded-lg ${getLeaveTypeColor(
            application.leaveTypeName.includes('Vacation') ? 'VL' :
            application.leaveTypeName.includes('Sick') ? 'SL' :
            application.leaveTypeName.includes('Service') ? 'SIL' : 'EL'
          )} flex items-center justify-center flex-shrink-0`}>
            <svg className="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <div className="flex flex-wrap items-center gap-2 mb-1">
              <span className="font-medium text-slate-900 dark:text-white">
                {application.leaveTypeName}
              </span>
              <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${getStatusColor(application.status)}`}>
                {application.status}
              </span>
              {application.halfDayType && (
                <span className="px-2 py-0.5 text-xs font-medium bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-full">
                  Half-day ({application.halfDayType.toUpperCase()})
                </span>
              )}
            </div>
            <p className="text-sm text-slate-600 dark:text-slate-400">
              {formatDateRange(application.startDate, application.endDate)}
              <span className="mx-2">•</span>
              {application.daysApplied} {application.daysApplied === 1 ? 'day' : 'days'}
            </p>
            <p className="text-sm text-slate-500 dark:text-slate-500 mt-1 line-clamp-1">
              {application.reason}
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2 sm:flex-shrink-0">
          {application.status === 'pending' && (
            <button
              onClick={(e) => {
                e.stopPropagation()
                onCancel?.()
              }}
              className="px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
            >
              Cancel
            </button>
          )}
          <span className="text-xs text-slate-400 dark:text-slate-500">
            {application.applicationNumber}
          </span>
        </div>
      </div>
      {application.status === 'rejected' && application.rejectionReason && (
        <div className="mt-3 ml-14 p-3 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-900/30 rounded-lg">
          <p className="text-sm text-red-700 dark:text-red-300">
            <span className="font-medium">Reason:</span> {application.rejectionReason}
          </p>
        </div>
      )}
    </div>
  )
}

interface LeaveApplicationDetailProps {
  application: LeaveApplication
  onClose: () => void
  onCancel?: () => void
}

function LeaveApplicationDetail({ application, onClose, onCancel }: LeaveApplicationDetailProps) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-xl overflow-hidden">
        {/* Header */}
        <div className={`p-6 ${
          application.status === 'approved' ? 'bg-gradient-to-r from-emerald-600 to-emerald-700' :
          application.status === 'pending' ? 'bg-gradient-to-r from-amber-500 to-amber-600' :
          application.status === 'rejected' ? 'bg-gradient-to-r from-red-600 to-red-700' :
          'bg-gradient-to-r from-slate-600 to-slate-700'
        }`}>
          <div className="flex items-start justify-between">
            <div>
              <p className="text-white/80 text-sm mb-1">{application.applicationNumber}</p>
              <h2 className="text-xl font-bold text-white">{application.leaveTypeName}</h2>
              <p className="text-white/80 text-sm mt-1">
                {formatDateRange(application.startDate, application.endDate)}
              </p>
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
        <div className="p-6 space-y-4">
          <div className="flex items-center justify-between p-4 bg-slate-50 dark:bg-slate-800/50 rounded-xl">
            <div>
              <p className="text-sm text-slate-500 dark:text-slate-400">Status</p>
              <span className={`inline-block mt-1 px-3 py-1 text-sm font-medium rounded-full ${getStatusColor(application.status)}`}>
                {application.status}
              </span>
            </div>
            <div className="text-right">
              <p className="text-sm text-slate-500 dark:text-slate-400">Duration</p>
              <p className="text-lg font-semibold text-slate-900 dark:text-white">
                {application.daysApplied} {application.daysApplied === 1 ? 'day' : 'days'}
                {application.halfDayType && ` (${application.halfDayType.toUpperCase()})`}
              </p>
            </div>
          </div>

          <div>
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Reason</p>
            <p className="text-slate-900 dark:text-white">{application.reason}</p>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Filed Date</p>
              <p className="text-slate-900 dark:text-white">{formatDate(application.filedDate)}</p>
            </div>
            <div>
              <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Approver</p>
              <p className="text-slate-900 dark:text-white">{application.approver.name}</p>
            </div>
          </div>

          {application.approvedAt && (
            <div>
              <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Approved On</p>
              <p className="text-slate-900 dark:text-white">{formatDate(application.approvedAt)}</p>
            </div>
          )}

          {application.rejectionReason && (
            <div className="p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
              <p className="text-sm font-medium text-red-800 dark:text-red-200 mb-1">Rejection Reason</p>
              <p className="text-sm text-red-700 dark:text-red-300">{application.rejectionReason}</p>
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
          {application.status === 'pending' && (
            <button
              onClick={onCancel}
              className="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors"
            >
              Cancel Request
            </button>
          )}
        </div>
      </div>
    </div>
  )
}

// =============================================================================
// Main Component
// =============================================================================

export function LeaveManagement({
  leaveBalances,
  leaveApplications,
  onFileLeave,
  onViewLeaveApplication,
  onCancelLeaveApplication,
  onBack,
}: LeaveManagementProps) {
  const [selectedApplicationId, setSelectedApplicationId] = useState<string | null>(null)
  const [statusFilter, setStatusFilter] = useState<string>('all')

  const selectedApplication = selectedApplicationId
    ? leaveApplications.find(a => a.id === selectedApplicationId)
    : null

  const filteredApplications = statusFilter === 'all'
    ? leaveApplications
    : leaveApplications.filter(a => a.status === statusFilter)

  const totalAvailable = leaveBalances.reduce((sum, b) => sum + b.available, 0)
  const pendingCount = leaveApplications.filter(a => a.status === 'pending').length

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div className="flex items-center gap-4">
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
                  Leave Management
                </h1>
                <p className="mt-1 text-slate-500 dark:text-slate-400">
                  {totalAvailable} days available • {pendingCount} pending
                </p>
              </div>
            </div>
            <button
              onClick={onFileLeave}
              className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
              </svg>
              File Leave
            </button>
          </div>
        </div>

        {/* Leave Balances */}
        <div className="mb-8">
          <h2 className="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-4">
            Leave Balances
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {leaveBalances.map((balance) => (
              <LeaveBalanceCard key={balance.id} balance={balance} />
            ))}
          </div>
        </div>

        {/* Leave Applications */}
        <div>
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <h2 className="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              Leave Applications
            </h2>
            <div className="flex items-center gap-2">
              <label className="text-sm text-slate-500 dark:text-slate-400">Filter:</label>
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="px-3 py-1.5 text-sm bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              >
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="rejected">Rejected</option>
                <option value="cancelled">Cancelled</option>
              </select>
            </div>
          </div>

          <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            {filteredApplications.length === 0 ? (
              <div className="p-12 text-center">
                <svg className="w-16 h-16 mx-auto mb-4 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p className="text-slate-500 dark:text-slate-400 mb-4">No leave applications found</p>
                <button
                  onClick={onFileLeave}
                  className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                  </svg>
                  File your first leave
                </button>
              </div>
            ) : (
              <div className="divide-y divide-slate-100 dark:divide-slate-800">
                {filteredApplications.map((application) => (
                  <LeaveApplicationRow
                    key={application.id}
                    application={application}
                    onView={() => {
                      setSelectedApplicationId(application.id)
                      onViewLeaveApplication?.(application.id)
                    }}
                    onCancel={() => onCancelLeaveApplication?.(application.id)}
                  />
                ))}
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Detail Modal */}
      {selectedApplication && (
        <LeaveApplicationDetail
          application={selectedApplication}
          onClose={() => setSelectedApplicationId(null)}
          onCancel={() => onCancelLeaveApplication?.(selectedApplication.id)}
        />
      )}
    </div>
  )
}
