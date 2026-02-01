import { useState } from 'react'
import type { LeaveApprovalQueueProps, LeaveApplication, Employee, LeaveType } from '../types'

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

function RequestCard({
  request,
  employee,
  leaveType,
  onApprove,
  onReject,
  onViewDetails,
}: {
  request: LeaveApplication
  employee?: Employee
  leaveType?: LeaveType
  onApprove?: (remarks?: string) => void
  onReject?: (remarks: string) => void
  onViewDetails?: () => void
}) {
  const [showRejectModal, setShowRejectModal] = useState(false)
  const [rejectRemarks, setRejectRemarks] = useState('')
  const colors = colorMap[leaveType?.color || 'slate']

  const formatDate = (date: string) => new Date(date).toLocaleDateString('en-PH', {
    weekday: 'short',
    month: 'short',
    day: 'numeric'
  })
  const formatDateTime = (date: string) => new Date(date).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit'
  })

  const daysSinceFilded = Math.floor((Date.now() - new Date(request.filedAt).getTime()) / (1000 * 60 * 60 * 24))

  return (
    <>
      <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden transition-all hover:shadow-lg">
        {/* Header */}
        <div className={`px-5 py-3 ${colors.bg} border-b ${colors.border} flex items-center justify-between`}>
          <div className="flex items-center gap-3">
            <span className={`font-semibold text-sm ${colors.text}`}>{leaveType?.code || 'N/A'}</span>
            <span className="text-sm text-slate-600 dark:text-slate-300">{leaveType?.name}</span>
          </div>
          <span className="text-xs text-slate-500 dark:text-slate-400">
            Filed {formatDateTime(request.filedAt)}
          </span>
        </div>

        <div className="p-5">
          {/* Employee Info */}
          <div className="flex items-start gap-4 mb-4">
            <div className={`w-12 h-12 rounded-full ${colors.bg} ${colors.text} flex items-center justify-center font-semibold text-lg flex-shrink-0`}>
              {employee ? `${employee.firstName[0]}${employee.lastName[0]}` : '??'}
            </div>
            <div className="flex-1 min-w-0">
              <h3 className="font-semibold text-slate-900 dark:text-white">
                {employee ? `${employee.firstName} ${employee.lastName}` : 'Unknown Employee'}
              </h3>
              <p className="text-sm text-slate-500 dark:text-slate-400">
                {employee?.position} &middot; {employee?.department}
              </p>
              <p className="text-xs text-slate-400 dark:text-slate-500 mt-0.5">
                {employee?.employeeNumber}
              </p>
            </div>
            {daysSinceFilded >= 2 && (
              <span className="px-2 py-1 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-xs font-medium rounded-full">
                {daysSinceFilded} days pending
              </span>
            )}
          </div>

          {/* Leave Details */}
          <div className="bg-slate-50 dark:bg-slate-700/30 rounded-lg p-4 mb-4">
            <div className="grid grid-cols-3 gap-4">
              <div>
                <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Start Date</p>
                <p className="font-medium text-slate-900 dark:text-white text-sm">{formatDate(request.startDate)}</p>
              </div>
              <div>
                <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">End Date</p>
                <p className="font-medium text-slate-900 dark:text-white text-sm">{formatDate(request.endDate)}</p>
              </div>
              <div>
                <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Duration</p>
                <p className={`font-semibold text-sm ${colors.text}`}>
                  {request.totalDays} {request.totalDays === 1 ? 'day' : 'days'}
                </p>
              </div>
            </div>
          </div>

          {/* Reason */}
          <div className="mb-4">
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-1">Reason</p>
            <p className="text-sm text-slate-700 dark:text-slate-200 leading-relaxed">{request.reason}</p>
          </div>

          {/* Attachments */}
          {request.attachments.length > 0 && (
            <div className="mb-4">
              <p className="text-xs text-slate-500 dark:text-slate-400 mb-2">Attachments</p>
              <div className="flex flex-wrap gap-2">
                {request.attachments.map(att => (
                  <span
                    key={att.id}
                    className="inline-flex items-center gap-1.5 px-2.5 py-1.5 bg-slate-100 dark:bg-slate-700 rounded-lg text-xs text-slate-600 dark:text-slate-300"
                  >
                    <svg className="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                    </svg>
                    {att.fileName}
                  </span>
                ))}
              </div>
            </div>
          )}

          {/* Actions */}
          <div className="flex items-center gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
            <button
              onClick={() => onApprove?.()}
              className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-medium rounded-lg transition-colors"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
              </svg>
              Approve
            </button>
            <button
              onClick={() => setShowRejectModal(true)}
              className="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-white dark:bg-slate-700 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-600 transition-colors"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
              Reject
            </button>
            <button
              onClick={onViewDetails}
              className="p-2.5 text-slate-400 hover:text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-700 dark:hover:text-slate-300 rounded-lg transition-colors"
            >
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
              </svg>
            </button>
          </div>
        </div>
      </div>

      {/* Reject Modal */}
      {showRejectModal && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
          <div className="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-2">Reject Leave Request</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-4">
              Please provide a reason for rejecting this leave request from {employee?.firstName} {employee?.lastName}.
            </p>
            <textarea
              value={rejectRemarks}
              onChange={(e) => setRejectRemarks(e.target.value)}
              placeholder="Enter rejection reason..."
              className="w-full px-3 py-2 border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
              rows={3}
            />
            <div className="flex items-center gap-3 mt-4">
              <button
                onClick={() => setShowRejectModal(false)}
                className="flex-1 px-4 py-2 border border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 font-medium rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  onReject?.(rejectRemarks)
                  setShowRejectModal(false)
                  setRejectRemarks('')
                }}
                disabled={!rejectRemarks.trim()}
                className="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 disabled:bg-red-300 dark:disabled:bg-red-900 text-white font-medium rounded-lg transition-colors"
              >
                Reject Request
              </button>
            </div>
          </div>
        </div>
      )}
    </>
  )
}

export function LeaveApprovalQueue({
  requests,
  employees,
  leaveTypes,
  onApprove,
  onReject,
  onViewDetails,
  onFilterByDepartment,
  onFilterByType,
}: LeaveApprovalQueueProps) {
  const [selectedDepartment, setSelectedDepartment] = useState<string | null>(null)
  const [selectedType, setSelectedType] = useState<string | null>(null)

  const getEmployee = (id: string) => employees.find(e => e.id === id)
  const getLeaveType = (id: string) => leaveTypes.find(t => t.id === id)

  const pendingRequests = requests.filter(r => r.status === 'pending')
  const departments = [...new Set(employees.map(e => e.department))]

  const filteredRequests = pendingRequests.filter(r => {
    const employee = getEmployee(r.employeeId)
    if (selectedDepartment && employee?.department !== selectedDepartment) return false
    if (selectedType && r.leaveTypeId !== selectedType) return false
    return true
  })

  // Sort by filed date (oldest first for FIFO)
  const sortedRequests = [...filteredRequests].sort(
    (a, b) => new Date(a.filedAt).getTime() - new Date(b.filedAt).getTime()
  )

  return (
    <div className="max-w-6xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white">Approval Queue</h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Review and process pending leave requests
          </p>
        </div>
        <div className="flex items-center gap-2">
          <span className="px-3 py-1.5 bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 text-sm font-medium rounded-full">
            {pendingRequests.length} pending
          </span>
        </div>
      </div>

      {/* Filters */}
      <div className="flex flex-wrap items-center gap-3 mb-6">
        <div className="flex items-center gap-2">
          <span className="text-sm text-slate-500 dark:text-slate-400">Filter by:</span>
        </div>
        <select
          value={selectedDepartment || ''}
          onChange={(e) => {
            const value = e.target.value || null
            setSelectedDepartment(value)
            onFilterByDepartment?.(value)
          }}
          className="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Departments</option>
          {departments.map(dept => (
            <option key={dept} value={dept}>{dept}</option>
          ))}
        </select>
        <select
          value={selectedType || ''}
          onChange={(e) => {
            const value = e.target.value || null
            setSelectedType(value)
            onFilterByType?.(value)
          }}
          className="px-3 py-2 text-sm border border-slate-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
        >
          <option value="">All Leave Types</option>
          {leaveTypes.map(type => (
            <option key={type.id} value={type.id}>{type.name}</option>
          ))}
        </select>
        {(selectedDepartment || selectedType) && (
          <button
            onClick={() => {
              setSelectedDepartment(null)
              setSelectedType(null)
              onFilterByDepartment?.(null)
              onFilterByType?.(null)
            }}
            className="text-sm text-blue-600 dark:text-blue-400 hover:underline"
          >
            Clear filters
          </button>
        )}
      </div>

      {/* Queue Stats */}
      <div className="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <p className="text-xs text-slate-500 dark:text-slate-400">Total Pending</p>
          <p className="text-2xl font-bold text-slate-900 dark:text-white">{pendingRequests.length}</p>
        </div>
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <p className="text-xs text-slate-500 dark:text-slate-400">Vacation Requests</p>
          <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400">
            {pendingRequests.filter(r => getLeaveType(r.leaveTypeId)?.code === 'VL').length}
          </p>
        </div>
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <p className="text-xs text-slate-500 dark:text-slate-400">Sick Leave Requests</p>
          <p className="text-2xl font-bold text-amber-600 dark:text-amber-400">
            {pendingRequests.filter(r => getLeaveType(r.leaveTypeId)?.code === 'SL').length}
          </p>
        </div>
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-4">
          <p className="text-xs text-slate-500 dark:text-slate-400">Oldest Request</p>
          <p className="text-2xl font-bold text-slate-900 dark:text-white">
            {pendingRequests.length > 0
              ? `${Math.floor((Date.now() - new Date(sortedRequests[0]?.filedAt || '').getTime()) / (1000 * 60 * 60 * 24))}d`
              : '-'
            }
          </p>
        </div>
      </div>

      {/* Request Cards */}
      {sortedRequests.length === 0 ? (
        <div className="bg-white dark:bg-slate-800/50 rounded-xl border border-slate-200 dark:border-slate-700 p-12 text-center">
          <div className="w-16 h-16 rounded-full bg-emerald-100 dark:bg-emerald-900/30 flex items-center justify-center mx-auto mb-4">
            <svg className="w-8 h-8 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <h3 className="text-lg font-semibold text-slate-900 dark:text-white mb-2">All caught up!</h3>
          <p className="text-sm text-slate-500 dark:text-slate-400">
            {selectedDepartment || selectedType
              ? 'No pending requests match your filters.'
              : 'There are no pending leave requests to review.'
            }
          </p>
        </div>
      ) : (
        <div className="grid md:grid-cols-2 gap-4">
          {sortedRequests.map(request => (
            <RequestCard
              key={request.id}
              request={request}
              employee={getEmployee(request.employeeId)}
              leaveType={getLeaveType(request.leaveTypeId)}
              onApprove={(remarks) => onApprove?.(request.id, remarks)}
              onReject={(remarks) => onReject?.(request.id, remarks)}
              onViewDetails={() => onViewDetails?.(request.id)}
            />
          ))}
        </div>
      )}
    </div>
  )
}
