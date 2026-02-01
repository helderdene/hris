import { useState } from 'react'
import type {
  Employee,
  Payslip,
  LeaveBalance,
  LeaveApplication,
  DailyTimeRecord,
  DocumentRequest,
  OvertimeRequest,
  Loan,
  TeamMember,
  PendingApproval,
} from '../types'

// =============================================================================
// Sub-component Props
// =============================================================================

interface QuickActionProps {
  icon: React.ReactNode
  label: string
  onClick?: () => void
}

interface LeaveBalanceCardProps {
  balances: LeaveBalance[]
  onFileLeave?: () => void
}

interface PayslipCardProps {
  payslips: Payslip[]
  onViewPayslip?: (id: string) => void
  onDownloadPayslip?: (id: string) => void
}

interface LeaveApplicationCardProps {
  applications: LeaveApplication[]
  onViewApplication?: (id: string) => void
  onCancelApplication?: (id: string) => void
}

interface DTRSummaryCardProps {
  records: DailyTimeRecord[]
  onViewDTR?: (id: string) => void
}

interface LoanCardProps {
  loans: Loan[]
  onViewLoan?: (id: string) => void
}

interface TeamOverviewCardProps {
  members: TeamMember[]
  onViewMember?: (id: string) => void
}

interface ApprovalQueueCardProps {
  approvals: PendingApproval[]
  onApprove?: (id: string) => void
  onReject?: (id: string) => void
  onViewDetails?: (id: string) => void
}

// =============================================================================
// Dashboard Props
// =============================================================================

export interface DashboardProps {
  currentEmployee: Employee
  payslips: Payslip[]
  leaveBalances: LeaveBalance[]
  leaveApplications: LeaveApplication[]
  dailyTimeRecords: DailyTimeRecord[]
  documentRequests: DocumentRequest[]
  overtimeRequests: OvertimeRequest[]
  loans: Loan[]
  teamMembers: TeamMember[]
  pendingApprovals: PendingApproval[]

  // Actions
  onUpdateProfile?: () => void
  onViewPayslip?: (id: string) => void
  onDownloadPayslip?: (id: string) => void
  onFileLeave?: () => void
  onViewLeaveApplication?: (id: string) => void
  onCancelLeaveApplication?: (id: string) => void
  onViewDTR?: (id: string) => void
  onRequestDTRCorrection?: (id: string) => void
  onFileOvertime?: () => void
  onRequestDocument?: () => void
  onViewLoan?: (id: string) => void
  onApplyLoan?: () => void
  onViewTeamMember?: (id: string) => void
  onApproveRequest?: (id: string) => void
  onRejectRequest?: (id: string, reason: string) => void
}

// =============================================================================
// Utility Functions
// =============================================================================

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP',
    minimumFractionDigits: 2,
  }).format(amount)
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
}

function formatTime(timeString: string | null): string {
  if (!timeString) return '--:--'
  const date = new Date(timeString)
  return date.toLocaleTimeString('en-PH', {
    hour: '2-digit',
    minute: '2-digit',
    hour12: true,
  })
}

function getStatusColor(status: string): string {
  switch (status) {
    case 'approved':
    case 'completed':
    case 'paid':
    case 'present':
      return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
    case 'pending':
    case 'processing':
      return 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
    case 'rejected':
    case 'cancelled':
    case 'absent':
      return 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'
    case 'leave':
      return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
    case 'holiday':
      return 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400'
    case 'late':
      return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
    default:
      return 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-400'
  }
}

function getUrgencyIndicator(urgency: string): string {
  switch (urgency) {
    case 'high':
      return 'border-l-red-500'
    case 'normal':
      return 'border-l-amber-500'
    case 'low':
      return 'border-l-slate-400'
    default:
      return 'border-l-slate-300'
  }
}

function getApprovalTypeIcon(type: string): React.ReactNode {
  switch (type) {
    case 'leave':
      return (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      )
    case 'overtime':
      return (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      )
    case 'dtr_correction':
      return (
        <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
        </svg>
      )
    default:
      return null
  }
}

// =============================================================================
// Sub-components
// =============================================================================

function QuickAction({ icon, label, onClick }: QuickActionProps) {
  return (
    <button
      onClick={onClick}
      className="flex flex-col items-center gap-2 p-4 rounded-xl bg-slate-50 dark:bg-slate-800/50 hover:bg-blue-50 dark:hover:bg-blue-900/20 transition-all duration-200 group border border-slate-200 dark:border-slate-700 hover:border-blue-300 dark:hover:border-blue-700"
    >
      <div className="w-10 h-10 rounded-lg bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400 flex items-center justify-center group-hover:scale-110 transition-transform">
        {icon}
      </div>
      <span className="text-xs font-medium text-slate-600 dark:text-slate-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 text-center">
        {label}
      </span>
    </button>
  )
}

function LeaveBalanceCard({ balances, onFileLeave }: LeaveBalanceCardProps) {
  const totalAvailable = balances.reduce((sum, b) => sum + b.available, 0)

  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div className="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-emerald-400 to-emerald-600 flex items-center justify-center text-white">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
          </div>
          <div>
            <h3 className="font-semibold text-slate-900 dark:text-white">Leave Balance</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">{totalAvailable} days available</p>
          </div>
        </div>
        <button
          onClick={onFileLeave}
          className="px-3 py-1.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-lg transition-colors"
        >
          File Leave
        </button>
      </div>
      <div className="p-5 space-y-4">
        {balances.map((balance) => (
          <div key={balance.id} className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <span className="w-10 h-6 rounded bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-400 flex items-center justify-center">
                {balance.leaveTypeCode}
              </span>
              <span className="text-sm text-slate-700 dark:text-slate-300">{balance.leaveTypeName}</span>
            </div>
            <div className="flex items-center gap-3">
              <div className="w-24 h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                <div
                  className="h-full bg-emerald-500 rounded-full transition-all"
                  style={{ width: `${(balance.available / balance.earned) * 100}%` }}
                />
              </div>
              <span className="text-sm font-semibold text-slate-900 dark:text-white w-12 text-right">
                {balance.available}
              </span>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

function PayslipCard({ payslips, onViewPayslip, onDownloadPayslip }: PayslipCardProps) {
  const latestPayslip = payslips[0]

  if (!latestPayslip) return null

  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div className="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
          </div>
          <div>
            <h3 className="font-semibold text-slate-900 dark:text-white">Latest Payslip</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">{latestPayslip.periodCode}</p>
          </div>
        </div>
        <span className={`px-2.5 py-1 text-xs font-medium rounded-full ${getStatusColor(latestPayslip.status)}`}>
          {latestPayslip.status}
        </span>
      </div>
      <div className="p-5">
        <div className="mb-4">
          <p className="text-sm text-slate-500 dark:text-slate-400 mb-1">Net Pay</p>
          <p className="text-3xl font-bold text-slate-900 dark:text-white font-mono tracking-tight">
            {formatCurrency(latestPayslip.netPay)}
          </p>
        </div>
        <div className="grid grid-cols-2 gap-4 mb-4">
          <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Gross Pay</p>
            <p className="text-sm font-semibold text-slate-900 dark:text-white font-mono">
              {formatCurrency(latestPayslip.grossPay)}
            </p>
          </div>
          <div className="p-3 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
            <p className="text-xs text-slate-500 dark:text-slate-400 mb-0.5">Deductions</p>
            <p className="text-sm font-semibold text-red-600 dark:text-red-400 font-mono">
              -{formatCurrency(latestPayslip.totalDeductions)}
            </p>
          </div>
        </div>
        <div className="flex gap-2">
          <button
            onClick={() => onViewPayslip?.(latestPayslip.id)}
            className="flex-1 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-lg transition-colors"
          >
            View Details
          </button>
          <button
            onClick={() => onDownloadPayslip?.(latestPayslip.id)}
            className="px-4 py-2 text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors flex items-center gap-2"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            PDF
          </button>
        </div>
      </div>
    </div>
  )
}

function LeaveApplicationCard({ applications, onViewApplication, onCancelApplication }: LeaveApplicationCardProps) {
  const pendingApplications = applications.filter(a => a.status === 'pending')
  const recentApplications = applications.slice(0, 3)

  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div className="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-400 to-amber-600 flex items-center justify-center text-white">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
          </div>
          <div>
            <h3 className="font-semibold text-slate-900 dark:text-white">Leave Applications</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              {pendingApplications.length} pending
            </p>
          </div>
        </div>
      </div>
      <div className="divide-y divide-slate-100 dark:divide-slate-800">
        {recentApplications.map((application) => (
          <div
            key={application.id}
            className="p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors cursor-pointer"
            onClick={() => onViewApplication?.(application.id)}
          >
            <div className="flex items-start justify-between gap-3">
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-sm font-medium text-slate-900 dark:text-white">
                    {application.leaveTypeName}
                  </span>
                  <span className={`px-2 py-0.5 text-xs font-medium rounded-full ${getStatusColor(application.status)}`}>
                    {application.status}
                  </span>
                </div>
                <p className="text-sm text-slate-500 dark:text-slate-400">
                  {formatDate(application.startDate)}
                  {application.startDate !== application.endDate && ` - ${formatDate(application.endDate)}`}
                  {' '}({application.daysApplied} {application.daysApplied === 1 ? 'day' : 'days'})
                </p>
              </div>
              {application.status === 'pending' && (
                <button
                  onClick={(e) => {
                    e.stopPropagation()
                    onCancelApplication?.(application.id)
                  }}
                  className="text-xs text-red-600 dark:text-red-400 hover:underline"
                >
                  Cancel
                </button>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

function DTRSummaryCard({ records, onViewDTR }: DTRSummaryCardProps) {
  const recentRecords = records.slice(0, 5)
  const totalLateMinutes = records.reduce((sum, r) => sum + r.lateMinutes, 0)
  const totalOTMinutes = records.reduce((sum, r) => sum + r.overtimeMinutes, 0)

  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div className="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-purple-400 to-purple-600 flex items-center justify-center text-white">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </div>
          <div>
            <h3 className="font-semibold text-slate-900 dark:text-white">Time Records</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">This month</p>
          </div>
        </div>
      </div>
      <div className="p-5">
        <div className="grid grid-cols-2 gap-3 mb-4">
          <div className="p-3 bg-orange-50 dark:bg-orange-900/20 rounded-lg border border-orange-100 dark:border-orange-900/30">
            <p className="text-xs text-orange-600 dark:text-orange-400 mb-0.5">Late (mins)</p>
            <p className="text-lg font-bold text-orange-700 dark:text-orange-300">{totalLateMinutes}</p>
          </div>
          <div className="p-3 bg-emerald-50 dark:bg-emerald-900/20 rounded-lg border border-emerald-100 dark:border-emerald-900/30">
            <p className="text-xs text-emerald-600 dark:text-emerald-400 mb-0.5">OT (mins)</p>
            <p className="text-lg font-bold text-emerald-700 dark:text-emerald-300">{totalOTMinutes}</p>
          </div>
        </div>
        <div className="space-y-2">
          {recentRecords.map((record) => (
            <div
              key={record.id}
              onClick={() => onViewDTR?.(record.id)}
              className="flex items-center justify-between p-2 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800/50 cursor-pointer transition-colors"
            >
              <div className="flex items-center gap-3">
                <span className="text-xs font-medium text-slate-500 dark:text-slate-400 w-16">
                  {new Date(record.workDate).toLocaleDateString('en-PH', { weekday: 'short', day: 'numeric' })}
                </span>
                <span className={`px-2 py-0.5 text-xs font-medium rounded ${getStatusColor(record.status)}`}>
                  {record.status}
                </span>
              </div>
              <div className="text-xs text-slate-500 dark:text-slate-400 font-mono">
                {formatTime(record.actualTimeIn)} - {formatTime(record.actualTimeOut)}
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

function LoanCard({ loans, onViewLoan }: LoanCardProps) {
  if (loans.length === 0) return null

  const totalBalance = loans.reduce((sum, l) => sum + l.remainingBalance, 0)

  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div className="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-rose-400 to-rose-600 flex items-center justify-center text-white">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <div>
            <h3 className="font-semibold text-slate-900 dark:text-white">Active Loans</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              {formatCurrency(totalBalance)} remaining
            </p>
          </div>
        </div>
      </div>
      <div className="divide-y divide-slate-100 dark:divide-slate-800">
        {loans.map((loan) => (
          <div
            key={loan.id}
            onClick={() => onViewLoan?.(loan.id)}
            className="p-4 hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors cursor-pointer"
          >
            <div className="flex items-center justify-between mb-2">
              <span className="text-sm font-medium text-slate-900 dark:text-white">
                {loan.loanTypeName}
              </span>
              <span className="text-xs text-slate-500 dark:text-slate-400">
                {loan.remainingMonths} months left
              </span>
            </div>
            <div className="flex items-center gap-3">
              <div className="flex-1 h-2 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                <div
                  className="h-full bg-rose-500 rounded-full transition-all"
                  style={{ width: `${(loan.totalPaid / loan.principalAmount) * 100}%` }}
                />
              </div>
              <span className="text-sm font-semibold text-slate-900 dark:text-white font-mono">
                {formatCurrency(loan.monthlyAmortization)}/mo
              </span>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

function TeamOverviewCard({ members, onViewMember }: TeamOverviewCardProps) {
  const presentCount = members.filter(m => m.attendanceStatus === 'present').length
  const onLeaveCount = members.filter(m => m.attendanceStatus === 'leave').length

  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div className="p-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center text-white">
            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
          </div>
          <div>
            <h3 className="font-semibold text-slate-900 dark:text-white">My Team</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400">
              {presentCount} present, {onLeaveCount} on leave
            </p>
          </div>
        </div>
      </div>
      <div className="p-4">
        <div className="flex flex-wrap gap-2">
          {members.map((member) => (
            <button
              key={member.id}
              onClick={() => onViewMember?.(member.id)}
              className="group relative"
              title={member.name}
            >
              <div className="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden ring-2 ring-white dark:ring-slate-900">
                <div className="w-full h-full flex items-center justify-center text-sm font-medium text-slate-600 dark:text-slate-300">
                  {member.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
                </div>
              </div>
              <span className={`absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full border-2 border-white dark:border-slate-900 ${
                member.attendanceStatus === 'present' ? 'bg-emerald-500' :
                member.attendanceStatus === 'leave' ? 'bg-blue-500' :
                member.attendanceStatus === 'late' ? 'bg-orange-500' :
                'bg-slate-400'
              }`} />
            </button>
          ))}
        </div>
      </div>
    </div>
  )
}

function ApprovalQueueCard({ approvals, onApprove, onReject, onViewDetails }: ApprovalQueueCardProps) {
  const [selectedIds, setSelectedIds] = useState<string[]>([])

  const toggleSelection = (id: string) => {
    setSelectedIds(prev =>
      prev.includes(id) ? prev.filter(i => i !== id) : [...prev, id]
    )
  }

  const highUrgencyCount = approvals.filter(a => a.urgency === 'high').length

  return (
    <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
      <div className="p-5 border-b border-slate-100 dark:border-slate-800">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-400 to-red-500 flex items-center justify-center text-white">
              <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div>
              <h3 className="font-semibold text-slate-900 dark:text-white">Pending Approvals</h3>
              <p className="text-sm text-slate-500 dark:text-slate-400">
                {approvals.length} items
                {highUrgencyCount > 0 && (
                  <span className="text-red-600 dark:text-red-400"> ({highUrgencyCount} urgent)</span>
                )}
              </p>
            </div>
          </div>
        </div>
        {selectedIds.length > 0 && (
          <div className="flex items-center gap-2 p-2 bg-slate-50 dark:bg-slate-800/50 rounded-lg">
            <span className="text-sm text-slate-600 dark:text-slate-400">
              {selectedIds.length} selected
            </span>
            <div className="flex-1" />
            <button
              onClick={() => {
                selectedIds.forEach(id => onApprove?.(id))
                setSelectedIds([])
              }}
              className="px-3 py-1 text-sm font-medium text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded transition-colors"
            >
              Approve All
            </button>
            <button
              onClick={() => {
                selectedIds.forEach(id => onReject?.(id))
                setSelectedIds([])
              }}
              className="px-3 py-1 text-sm font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
            >
              Reject All
            </button>
          </div>
        )}
      </div>
      <div className="divide-y divide-slate-100 dark:divide-slate-800 max-h-[400px] overflow-y-auto">
        {approvals.map((approval) => (
          <div
            key={approval.id}
            className={`p-4 border-l-4 ${getUrgencyIndicator(approval.urgency)} hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors`}
          >
            <div className="flex items-start gap-3">
              <input
                type="checkbox"
                checked={selectedIds.includes(approval.id)}
                onChange={() => toggleSelection(approval.id)}
                className="mt-1 w-4 h-4 rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500"
              />
              <div className="w-8 h-8 rounded-full bg-slate-200 dark:bg-slate-700 overflow-hidden flex-shrink-0">
                <div className="w-full h-full flex items-center justify-center text-xs font-medium text-slate-600 dark:text-slate-300">
                  {approval.employeeName.split(' ').map(n => n[0]).join('').slice(0, 2)}
                </div>
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-1">
                  <span className="text-slate-400 dark:text-slate-500">
                    {getApprovalTypeIcon(approval.type)}
                  </span>
                  <span className="text-sm font-medium text-slate-900 dark:text-white truncate">
                    {approval.employeeName}
                  </span>
                  {approval.urgency === 'high' && (
                    <span className="px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 rounded">
                      Urgent
                    </span>
                  )}
                </div>
                <p className="text-sm text-slate-600 dark:text-slate-400 mb-1">
                  {approval.description}
                </p>
                <p className="text-xs text-slate-400 dark:text-slate-500">
                  {approval.reason}
                </p>
              </div>
              <div className="flex items-center gap-1 flex-shrink-0">
                <button
                  onClick={() => onApprove?.(approval.id)}
                  className="p-1.5 text-emerald-600 dark:text-emerald-400 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 rounded transition-colors"
                  title="Approve"
                >
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                  </svg>
                </button>
                <button
                  onClick={() => onReject?.(approval.id)}
                  className="p-1.5 text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                  title="Reject"
                >
                  <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                  </svg>
                </button>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  )
}

// =============================================================================
// Main Dashboard Component
// =============================================================================

export function Dashboard({
  currentEmployee,
  payslips,
  leaveBalances,
  leaveApplications,
  dailyTimeRecords,
  documentRequests,
  overtimeRequests,
  loans,
  teamMembers,
  pendingApprovals,
  onUpdateProfile,
  onViewPayslip,
  onDownloadPayslip,
  onFileLeave,
  onViewLeaveApplication,
  onCancelLeaveApplication,
  onViewDTR,
  onRequestDTRCorrection,
  onFileOvertime,
  onRequestDocument,
  onViewLoan,
  onApplyLoan,
  onViewTeamMember,
  onApproveRequest,
  onRejectRequest,
}: DashboardProps) {
  const isManager = teamMembers.length > 0
  const pendingLeaves = leaveApplications.filter(l => l.status === 'pending').length
  const processingDocs = documentRequests.filter(d => d.status === 'processing').length

  return (
    <div className="min-h-screen bg-slate-50 dark:bg-slate-950">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8">
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
              <h1 className="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white">
                Welcome back, {currentEmployee.firstName}
              </h1>
              <p className="mt-1 text-slate-500 dark:text-slate-400">
                {currentEmployee.position} &middot; {currentEmployee.department}
              </p>
            </div>
            <button
              onClick={onUpdateProfile}
              className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors"
            >
              <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
              </svg>
              View Profile
            </button>
          </div>
        </div>

        {/* Quick Actions */}
        <div className="mb-8">
          <h2 className="text-sm font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-4">
            Quick Actions
          </h2>
          <div className="grid grid-cols-3 sm:grid-cols-6 gap-3">
            <QuickAction
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              }
              label="File Leave"
              onClick={onFileLeave}
            />
            <QuickAction
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              }
              label="File OT"
              onClick={onFileOvertime}
            />
            <QuickAction
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
              }
              label="Request Doc"
              onClick={onRequestDocument}
            />
            <QuickAction
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
              }
              label="Apply Loan"
              onClick={onApplyLoan}
            />
            <QuickAction
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
              }
              label="View DTR"
              onClick={() => onViewDTR?.(dailyTimeRecords[0]?.id)}
            />
            <QuickAction
              icon={
                <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                </svg>
              }
              label="Payslips"
              onClick={() => onViewPayslip?.(payslips[0]?.id)}
            />
          </div>
        </div>

        {/* Status Alerts */}
        {(pendingLeaves > 0 || processingDocs > 0 || pendingApprovals.length > 0) && (
          <div className="mb-8 space-y-3">
            {pendingLeaves > 0 && (
              <div className="flex items-center gap-3 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
                <div className="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-900/40 flex items-center justify-center text-amber-600 dark:text-amber-400">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                </div>
                <p className="text-sm text-amber-800 dark:text-amber-200">
                  You have <span className="font-semibold">{pendingLeaves} pending leave request{pendingLeaves > 1 ? 's' : ''}</span> awaiting approval
                </p>
              </div>
            )}
            {processingDocs > 0 && (
              <div className="flex items-center gap-3 p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-xl">
                <div className="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/40 flex items-center justify-center text-blue-600 dark:text-blue-400">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                  </svg>
                </div>
                <p className="text-sm text-blue-800 dark:text-blue-200">
                  <span className="font-semibold">{processingDocs} document request{processingDocs > 1 ? 's' : ''}</span> being processed
                </p>
              </div>
            )}
            {isManager && pendingApprovals.length > 0 && (
              <div className="flex items-center gap-3 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl">
                <div className="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center text-red-600 dark:text-red-400">
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                  </svg>
                </div>
                <p className="text-sm text-red-800 dark:text-red-200">
                  <span className="font-semibold">{pendingApprovals.length} item{pendingApprovals.length > 1 ? 's' : ''}</span> need your approval
                </p>
              </div>
            )}
          </div>
        )}

        {/* Main Content Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Left Column */}
          <div className="space-y-6">
            <PayslipCard
              payslips={payslips}
              onViewPayslip={onViewPayslip}
              onDownloadPayslip={onDownloadPayslip}
            />
            <LeaveBalanceCard
              balances={leaveBalances}
              onFileLeave={onFileLeave}
            />
            <LoanCard
              loans={loans}
              onViewLoan={onViewLoan}
            />
          </div>

          {/* Right Column */}
          <div className="space-y-6">
            {isManager && (
              <>
                <ApprovalQueueCard
                  approvals={pendingApprovals}
                  onApprove={onApproveRequest}
                  onReject={(id) => onRejectRequest?.(id, '')}
                  onViewDetails={onViewLeaveApplication}
                />
                <TeamOverviewCard
                  members={teamMembers}
                  onViewMember={onViewTeamMember}
                />
              </>
            )}
            <DTRSummaryCard
              records={dailyTimeRecords}
              onViewDTR={onViewDTR}
            />
            <LeaveApplicationCard
              applications={leaveApplications}
              onViewApplication={onViewLeaveApplication}
              onCancelApplication={onCancelLeaveApplication}
            />
          </div>
        </div>
      </div>
    </div>
  )
}
