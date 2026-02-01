import { useState } from 'react'
import type { PendingApproval, TeamMember } from '../types'

// =============================================================================
// Component Props
// =============================================================================

export interface ApprovalQueueProps {
  pendingApprovals: PendingApproval[]
  teamMembers: TeamMember[]
  onApproveRequest?: (id: string) => void
  onRejectRequest?: (id: string, reason: string) => void
  onBatchApprove?: (ids: string[]) => void
  onBatchReject?: (ids: string[], reason: string) => void
  onViewTeamMember?: (id: string) => void
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

function getTypeColor(type: string): string {
  switch (type) {
    case 'leave':
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'overtime':
      return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
    case 'dtr_correction':
      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
  }
}

function getTypeIcon(type: string): React.ReactNode {
  switch (type) {
    case 'leave':
      return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      )
    case 'overtime':
      return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      )
    case 'dtr_correction':
      return (
        <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
      )
    default:
      return null
  }
}

function getUrgencyBadge(urgency: string): React.ReactNode {
  switch (urgency) {
    case 'high':
      return (
        <span className="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded animate-pulse">
          Urgent
        </span>
      )
    case 'normal':
      return null
    case 'low':
      return (
        <span className="px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 rounded">
          Low
        </span>
      )
    default:
      return null
  }
}

function getAttendanceStatusColor(status: string): string {
  switch (status) {
    case 'present':
      return 'bg-emerald-500'
    case 'leave':
      return 'bg-blue-500'
    case 'late':
      return 'bg-orange-500'
    case 'absent':
      return 'bg-red-500'
    default:
      return 'bg-slate-400'
  }
}

// =============================================================================
// Sub-components
// =============================================================================

interface ApprovalCardProps {
  approval: PendingApproval
  isSelected: boolean
  onToggleSelect: () => void
  onApprove?: () => void
  onReject?: () => void
}

function ApprovalCard({
  approval,
  isSelected,
  onToggleSelect,
  onApprove,
  onReject,
}: ApprovalCardProps) {
  return (
    <div
      className={`p-4 border-l-4 transition-all ${
        approval.urgency === 'high' ? 'border-l-red-500' :
        approval.urgency === 'low' ? 'border-l-slate-300 dark:border-l-slate-600' :
        'border-l-amber-500'
      } ${isSelected ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-slate-50 dark:hover:bg-slate-800/50'}`}
    >
      <div className="flex items-start gap-4">
        <input
          type="checkbox"
          checked={isSelected}
          onChange={onToggleSelect}
          className="mt-1 w-5 h-5 rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500"
        />

        <div className="w-12 h-12 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden flex-shrink-0">
          <div className="w-full h-full flex items-center justify-center text-sm font-medium text-slate-600 dark:text-slate-300">
            {approval.employeeName.split(' ').map(n => n[0]).join('').slice(0, 2)}
          </div>
        </div>

        <div className="flex-1 min-w-0">
          <div className="flex flex-wrap items-center gap-2 mb-1">
            <span className="font-semibold text-slate-900 dark:text-white">
              {approval.employeeName}
            </span>
            <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${getTypeColor(approval.type)}`}>
              {approval.type.replace('_', ' ')}
            </span>
            {getUrgencyBadge(approval.urgency)}
          </div>

          <p className="text-slate-700 dark:text-slate-300 mb-1">
            {approval.description}
          </p>

          <p className="text-sm text-slate-500 dark:text-slate-400 line-clamp-2">
            {approval.reason}
          </p>

          <div className="flex items-center gap-4 mt-2 text-xs text-slate-400 dark:text-slate-500">
            <span>{approval.referenceNumber}</span>
            <span>Filed {formatDate(approval.filedDate)}</span>
          </div>
        </div>

        <div className="flex flex-col gap-2 flex-shrink-0">
          <button
            onClick={onApprove}
            className="px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg transition-colors flex items-center gap-2"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
            </svg>
            Approve
          </button>
          <button
            onClick={onReject}
            className="px-4 py-2 text-sm font-medium text-red-600 dark:text-red-400 bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/30 rounded-lg transition-colors flex items-center gap-2"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
            </svg>
            Reject
          </button>
        </div>
      </div>
    </div>
  )
}

interface TeamMemberCardProps {
  member: TeamMember
  onClick?: () => void
}

function TeamMemberCard({ member, onClick }: TeamMemberCardProps) {
  return (
    <button
      onClick={onClick}
      className="w-full text-left p-4 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-blue-300 dark:hover:border-blue-700 transition-colors"
    >
      <div className="flex items-center gap-3">
        <div className="relative">
          <div className="w-12 h-12 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden">
            <div className="w-full h-full flex items-center justify-center text-sm font-medium text-slate-600 dark:text-slate-300">
              {member.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
            </div>
          </div>
          <span className={`absolute -bottom-0.5 -right-0.5 w-4 h-4 rounded-full border-2 border-white dark:border-slate-900 ${getAttendanceStatusColor(member.attendanceStatus)}`} />
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-medium text-slate-900 dark:text-white truncate">{member.name}</p>
          <p className="text-sm text-slate-500 dark:text-slate-400 truncate">{member.position}</p>
        </div>
        {member.pendingLeaves > 0 && (
          <span className="px-2 py-1 text-xs font-medium bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 rounded-full">
            {member.pendingLeaves} pending
          </span>
        )}
      </div>
    </button>
  )
}

interface RejectModalProps {
  count: number
  onConfirm: (reason: string) => void
  onCancel: () => void
}

function RejectModal({ count, onConfirm, onCancel }: RejectModalProps) {
  const [reason, setReason] = useState('')

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div className="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-xl overflow-hidden">
        <div className="p-6 border-b border-slate-100 dark:border-slate-800">
          <h2 className="text-xl font-bold text-slate-900 dark:text-white">
            Reject {count} Request{count > 1 ? 's' : ''}
          </h2>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            Please provide a reason for rejection
          </p>
        </div>
        <div className="p-6">
          <textarea
            value={reason}
            onChange={(e) => setReason(e.target.value)}
            placeholder="Enter rejection reason..."
            className="w-full h-32 px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
          />
        </div>
        <div className="p-4 border-t border-slate-100 dark:border-slate-800 flex gap-3">
          <button
            onClick={onCancel}
            className="flex-1 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition-colors"
          >
            Cancel
          </button>
          <button
            onClick={() => onConfirm(reason)}
            disabled={!reason.trim()}
            className="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 disabled:bg-red-300 disabled:cursor-not-allowed rounded-lg transition-colors"
          >
            Reject
          </button>
        </div>
      </div>
    </div>
  )
}

// =============================================================================
// Main Component
// =============================================================================

export function ApprovalQueue({
  pendingApprovals,
  teamMembers,
  onApproveRequest,
  onRejectRequest,
  onBatchApprove,
  onBatchReject,
  onViewTeamMember,
  onBack,
}: ApprovalQueueProps) {
  const [selectedIds, setSelectedIds] = useState<string[]>([])
  const [typeFilter, setTypeFilter] = useState<string>('all')
  const [urgencyFilter, setUrgencyFilter] = useState<string>('all')
  const [showRejectModal, setShowRejectModal] = useState(false)
  const [rejectTargetIds, setRejectTargetIds] = useState<string[]>([])

  const toggleSelection = (id: string) => {
    setSelectedIds(prev =>
      prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
    )
  }

  const selectAll = () => {
    setSelectedIds(filteredApprovals.map(a => a.id))
  }

  const clearSelection = () => {
    setSelectedIds([])
  }

  const handleBatchApprove = () => {
    onBatchApprove?.(selectedIds)
    setSelectedIds([])
  }

  const handleReject = (ids: string[]) => {
    setRejectTargetIds(ids)
    setShowRejectModal(true)
  }

  const confirmReject = (reason: string) => {
    if (rejectTargetIds.length === 1) {
      onRejectRequest?.(rejectTargetIds[0], reason)
    } else {
      onBatchReject?.(rejectTargetIds, reason)
    }
    setRejectTargetIds([])
    setSelectedIds(prev => prev.filter(id => !rejectTargetIds.includes(id)))
    setShowRejectModal(false)
  }

  const filteredApprovals = pendingApprovals
    .filter(a => typeFilter === 'all' || a.type === typeFilter)
    .filter(a => urgencyFilter === 'all' || a.urgency === urgencyFilter)

  const highUrgencyCount = pendingApprovals.filter(a => a.urgency === 'high').length
  const presentCount = teamMembers.filter(m => m.attendanceStatus === 'present').length

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
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
                Approval Queue
              </h1>
              <p className="mt-1 text-slate-500 dark:text-slate-400">
                {pendingApprovals.length} pending
                {highUrgencyCount > 0 && (
                  <span className="text-red-600 dark:text-red-400"> ({highUrgencyCount} urgent)</span>
                )}
              </p>
            </div>
          </div>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Main Queue */}
          <div className="lg:col-span-2">
            {/* Filters & Actions */}
            <div className="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 p-4 mb-4">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div className="flex flex-wrap items-center gap-3">
                  <select
                    value={typeFilter}
                    onChange={(e) => setTypeFilter(e.target.value)}
                    className="px-3 py-1.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="all">All Types</option>
                    <option value="leave">Leave</option>
                    <option value="overtime">Overtime</option>
                    <option value="dtr_correction">DTR Correction</option>
                  </select>
                  <select
                    value={urgencyFilter}
                    onChange={(e) => setUrgencyFilter(e.target.value)}
                    className="px-3 py-1.5 text-sm bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="all">All Urgency</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                  </select>
                </div>

                {selectedIds.length > 0 ? (
                  <div className="flex items-center gap-2">
                    <span className="text-sm text-slate-600 dark:text-slate-400">
                      {selectedIds.length} selected
                    </span>
                    <button
                      onClick={handleBatchApprove}
                      className="px-3 py-1.5 text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded-lg transition-colors"
                    >
                      Approve All
                    </button>
                    <button
                      onClick={() => handleReject(selectedIds)}
                      className="px-3 py-1.5 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-lg transition-colors"
                    >
                      Reject All
                    </button>
                    <button
                      onClick={clearSelection}
                      className="px-3 py-1.5 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
                    >
                      Clear
                    </button>
                  </div>
                ) : (
                  <button
                    onClick={selectAll}
                    className="px-3 py-1.5 text-sm font-medium text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
                  >
                    Select All
                  </button>
                )}
              </div>
            </div>

            {/* Approval List */}
            <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
              {filteredApprovals.length === 0 ? (
                <div className="p-12 text-center">
                  <svg className="w-16 h-16 mx-auto mb-4 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <p className="text-lg font-medium text-slate-900 dark:text-white mb-1">All caught up!</p>
                  <p className="text-slate-500 dark:text-slate-400">No pending approvals</p>
                </div>
              ) : (
                <div className="divide-y divide-slate-100 dark:divide-slate-800">
                  {filteredApprovals.map((approval) => (
                    <ApprovalCard
                      key={approval.id}
                      approval={approval}
                      isSelected={selectedIds.includes(approval.id)}
                      onToggleSelect={() => toggleSelection(approval.id)}
                      onApprove={() => onApproveRequest?.(approval.id)}
                      onReject={() => handleReject([approval.id])}
                    />
                  ))}
                </div>
              )}
            </div>
          </div>

          {/* Sidebar - Team Overview */}
          <div className="lg:col-span-1">
            <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden sticky top-8">
              <div className="p-4 border-b border-slate-100 dark:border-slate-800">
                <h2 className="font-semibold text-slate-900 dark:text-white">My Team</h2>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  {presentCount} of {teamMembers.length} present today
                </p>
              </div>

              {/* Quick Stats */}
              <div className="p-4 border-b border-slate-100 dark:border-slate-800 grid grid-cols-4 gap-2">
                <div className="text-center p-2 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg">
                  <p className="text-lg font-bold text-emerald-600 dark:text-emerald-400">
                    {teamMembers.filter(m => m.attendanceStatus === 'present').length}
                  </p>
                  <p className="text-[10px] text-emerald-600 dark:text-emerald-400">Present</p>
                </div>
                <div className="text-center p-2 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                  <p className="text-lg font-bold text-blue-600 dark:text-blue-400">
                    {teamMembers.filter(m => m.attendanceStatus === 'leave').length}
                  </p>
                  <p className="text-[10px] text-blue-600 dark:text-blue-400">Leave</p>
                </div>
                <div className="text-center p-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg">
                  <p className="text-lg font-bold text-orange-600 dark:text-orange-400">
                    {teamMembers.filter(m => m.attendanceStatus === 'late').length}
                  </p>
                  <p className="text-[10px] text-orange-600 dark:text-orange-400">Late</p>
                </div>
                <div className="text-center p-2 bg-red-50 dark:bg-red-900/20 rounded-lg">
                  <p className="text-lg font-bold text-red-600 dark:text-red-400">
                    {teamMembers.filter(m => m.attendanceStatus === 'absent').length}
                  </p>
                  <p className="text-[10px] text-red-600 dark:text-red-400">Absent</p>
                </div>
              </div>

              {/* Team Members */}
              <div className="p-4 space-y-3 max-h-[500px] overflow-y-auto">
                {teamMembers.map((member) => (
                  <TeamMemberCard
                    key={member.id}
                    member={member}
                    onClick={() => onViewTeamMember?.(member.id)}
                  />
                ))}
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Reject Modal */}
      {showRejectModal && (
        <RejectModal
          count={rejectTargetIds.length}
          onConfirm={confirmReject}
          onCancel={() => {
            setShowRejectModal(false)
            setRejectTargetIds([])
          }}
        />
      )}
    </div>
  )
}
